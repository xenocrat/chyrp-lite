#!/usr/bin/env ruby

require "find"
require "optparse"

OPTIONS = {
  :exclude => [".git", "modules", "lib", "feathers", "themes", "config.yaml.php"]
}

ARGV.options do |o|
  script_name = File.basename($0)

  o.banner =    "Usage: #{script_name} [directory] [OPTIONS]"
  o.define_head "Scans [directory] recursively for Trigger calls and filters."

  o.separator ""

  o.on("--exclude=[val1,val1]", Array,
       "A list of directories to exclude from the scan.") { |OPTIONS[:exclude]| }

  o.separator ""

  o.on_tail("-h", "--help", "Show this help message.") do
    puts o
    exit
  end

  o.parse!
end

class Triggers
  def initialize(start)
    @start, @files, @triggers = start, [], { :calls => {}, :filters => {} }
    prepare_files
    do_scan
    output
  end

  def prepare_files
    Find.find(@start) do |path|
      cleaned = path.sub("./", "")
      if FileTest.directory?(path)
        if OPTIONS[:exclude].include?(cleaned)
          Find.prune
        else
          next
        end
      else
        next unless path =~ /\.(php|twig)/
        @files << [cleaned, path] if File.read(path) =~ /(\$trigger|Trigger::current\(\))->call\("[^"]+"(.*?)\)/
        @files << [cleaned, path] if File.read(path) =~ /(\$trigger|Trigger::current\(\))->filter\(([^,]+), ?"[^"]+"(.*?)\)/
        @files << [cleaned, path] if File.read(path) =~ /\$\{ ?trigger\.call\("[^"]+"(, ?(.+))?\) ?\}/
      end
    end
  end

  def do_scan
    @files.each do |cleaned, file|
      counter = 1
      File.open(file, "r") do |infile|
        while line = infile.gets
          line.gsub!("\\\"", "{QUOTE}") # So that [^"]+ doesn't match \"'s in the translation.
          scan_call        line, counter, file, cleaned
          scan_filter      line, counter, file, cleaned
          scan_twig_call   line, counter, file, cleaned
          counter += 1
        end
      end
    end
  end

  def scan_call(text, line, filename, clean_filename)
    text.gsub(/(\$trigger|Trigger::current\(\))->call\("([^"]+)"(, ?(.+))?\)/) do
      if @triggers[:calls][$2].nil?
        @triggers[:calls][$2] = { :places => [clean_filename + ":" + line.to_s],
                                  :arguments => $4 }
      elsif not @triggers[:calls][$2][:places].include?(clean_filename + ":" + line.to_s)
        @triggers[:calls][$2][:places] << clean_filename + ":" + line.to_s
      end
    end
  end

  def scan_filter(text, line, filename, clean_filename)
    text.gsub(/(\$trigger|Trigger::current\(\))->filter\(([^,]+), ?"([^"]+)"(, ?(.+))?\)/) do
      if @triggers[:filters][$3].nil?
        @triggers[:filters][$3] = { :places => [clean_filename + ":" + line.to_s],
                                    :target => $2,
                                    :arguments => $5 }
      elsif not @triggers[:filters][$3][:places].include?(clean_filename + ":" + line.to_s)
        @triggers[:filters][$3][:places] << clean_filename + ":" + line.to_s
      end
    end
  end

  def scan_twig_call(text, line, filename, clean_filename)
    text.gsub(/\$\{ ?trigger\.call\("([^"]+)"(, ?(.+))?\) ?\}/) do
      if @triggers[:calls][$1].nil?
        @triggers[:calls][$1] = { :places => [clean_filename + ":" + line.to_s],
                                  :arguments => $3 }
      elsif not @triggers[:calls][$1][:places].include?(clean_filename + ":" + line.to_s)
        @triggers[:calls][$1][:places] << clean_filename + ":" + line.to_s
      end
    end
  end

  def output
    puts "=============================================="
    puts " Trigger Calls"
    print "=============================================="
    @triggers[:calls].each do |name, trigger|
      puts "\n\n"
      puts name
      name.length.times do
        print "-"
      end
      puts "\n"

      puts "Called from:"

      trigger[:places].each { |place| puts "\t" + place.split(":").join(" on line ") }

      next if trigger[:arguments].nil?
      puts "Arguments:"
      puts "\t" + trigger[:arguments]
    end
    puts "\n\n\n"
    puts "=============================================="
    puts " Trigger Filters"
    print "=============================================="
    @triggers[:filters].each do |name, trigger|
      puts "\n\n"
      puts name
      name.length.times do
        print "-"
      end
      puts "\n"

      puts "Target:"
      puts "\t" + trigger[:target]

      puts "Called from:"

      trigger[:places].each { |place| puts "\t" + place.split(":").join(" on line ") }

      next if trigger[:arguments].nil?
      puts "Arguments:"
      puts "\t" + trigger[:arguments]
    end
  end
end

Triggers.new ARGV[0] || "."