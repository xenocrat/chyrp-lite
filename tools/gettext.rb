#!/usr/bin/env ruby
# encoding: UTF-8

require "find"
require "optparse"

OPTIONS = {
  :project => "Chyrp Lite",
  :maintainer => "xenocrat <hello@xenocrat.net>",
  :domain => nil,
  :msgstr => "",
  :msgstr_filter => "",
  :exclude => [".git", "modules", "lib", "feathers", "themes", "admin"]
}

# Shamelessly taken from the Twig lexer. :P
STRING = /(?:"([^"\\]*(?:\\.[^"\\]*)*)"|'([^'\\]*(?:\\.[^'\\]*)*)')/

ARGV.options do |o|
  script_name = File.basename($0)

  o.banner =    "Usage: #{script_name} [directory] [OPTIONS]"
  o.define_head "Scans [directory] recursively for various forms of gettext translations and outputs to a .pot file."

  o.separator ""

  o.on("--project=[val]", String,
       "The name of the project the .pot file is for.") do |project|
    OPTIONS[:project] = project
  end
  o.on("--maintainer=[val]", String,
       "The maintainer of the .pot file. (Firstname Lastname <foo@bar.com>)") do |maintainer|
    OPTIONS[:maintainer] = maintainer
  end
  o.on("--domain=[val]", String,
       "Domain to scan for translations.") do |domain|
    OPTIONS[:domain] = domain
  end
  o.on("--msgstr=[val]", String,
       "Message string to translate all found translations to. Useful for debugging.") do |msgstr|
    OPTIONS[:mststr] = msgstr
  end
  o.on("--exclude=[val1,val2]", Array,
       "A list of directories to exclude from the scan.") do |exclude|
    OPTIONS[:exclude] = exclude
  end

  o.separator ""

  o.on_tail("-h", "--help", "Show this help message.") do
    puts o
    exit
  end

  o.parse!
end

class Gettext
  def initialize(start)
    @start, @files, @translations = start, [], {}

    @domain = (OPTIONS[:domain].nil?) ? "" : ',\s*"'+OPTIONS[:domain]+'"'
    @twig_domain = (OPTIONS[:domain].nil? or OPTIONS[:domain] == "theme") ? "" : '\("'+OPTIONS[:domain]+'"\)'
    @twig_arg_domain = (OPTIONS[:domain].nil? or OPTIONS[:domain] == "theme") ? "" : ',\s*"'+OPTIONS[:domain]+'"'

    prepare_files
    do_scan
    print_pofile
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
        @files << [cleaned, path] if File.read(path) =~ /__\((#{STRING})#{@domain}\)/
        @files << [cleaned, path] if File.read(path) =~ /_f\((#{STRING}),.*?#{@domain}\)/
        @files << [cleaned, path] if File.read(path) =~ /_p\((#{STRING}),\s*(#{STRING}),.*?#{@domain}\)/
        @files << [cleaned, path] if File.read(path) =~ /Group::add_permission\(([^,]+),\s*(#{STRING})\)/
        @files << [cleaned, path] if File.read(path) =~ /(#{STRING})\s*\|\s*translate#{@twig_domain}/
        @files << [cleaned, path] if File.read(path) =~ /(#{STRING})\s*\|\s*translate_plural\((#{STRING}),\s*.*?#{@domain}\)\s*\|\s*format\(.*?\)/
      end
    end
  end

  def do_scan
    @files.each do |cleaned, file|
      counter = 1
      File.open(file, "r") do |infile|
        while line = infile.gets
          scan_normal      line, counter, file, cleaned
          scan_permissions line, counter, file, cleaned
          scan_filter      line, counter, file, cleaned
          scan_plural      line, counter, file, cleaned
          scan_twig        line, counter, file, cleaned
          scan_twig_filter line, counter, file, cleaned
          scan_twig_plural line, counter, file, cleaned
          counter += 1
        end
      end
    end
  end

  def scan_normal(text, line, filename, clean_filename)
    text.gsub(/__\((#{STRING})#{@domain}\)/) do
      if @translations[$1].nil?
        @translations[$1] = { :places => [clean_filename + ":" + line.to_s],
                              :filter => false,
                              :plural => false }
      elsif not @translations[$1][:places].include?(clean_filename + ":" + line.to_s)
        @translations[$1][:places] << clean_filename + ":" + line.to_s
      end
    end
  end

  def scan_permissions(text, line, filename, clean_filename)
    text.gsub(/Group::add_permission\(([^,]+),\s*(#{STRING})\)/) do
      if @translations[$2].nil?
        @translations[$2] = { :places => [clean_filename + ":" + line.to_s],
                              :filter => false,
                              :plural => false }
      elsif not @translations[$2][:places].include?(clean_filename + ":" + line.to_s)
        @translations[$2][:places] << clean_filename + ":" + line.to_s
      end
    end
  end

  def scan_filter(text, line, filename, clean_filename)
    text.gsub(/_f\((#{STRING}),.*?#{@domain}\)/) do
      if @translations[$1].nil?
        @translations[$1] = { :places => [clean_filename + ":" + line.to_s],
                              :filter => true,
                              :plural => false }
      elsif not @translations[$1][:places].include?(clean_filename + ":" + line.to_s)
        @translations[$1][:places] << clean_filename + ":" + line.to_s
      end
    end
  end

  def scan_plural(text, line, filename, clean_filename)
    text.gsub(/_p\((#{STRING}),\s*(#{STRING}),.*?#{@domain}\)/) do
      if @translations[$1].nil?
        @translations[$1] = { :places => [clean_filename + ":" + line.to_s],
                              :filter => true,
                              :plural => $4 }
      elsif not @translations[$1][:places].include?(clean_filename + ":" + line.to_s)
        @translations[$1][:places] << clean_filename + ":" + line.to_s
      end
    end
  end

  def scan_twig(text, line, filename, clean_filename)
    text.gsub(/[\s\{\(](#{STRING})\s*\|\s*translate(?!_plural)#{@twig_domain}(?!\s*\|\s*format)/) do
      if @translations[$1].nil?
        @translations[$1] = { :places => [clean_filename + ":" + line.to_s],
                              :filter => false,
                              :plural => false }
      elsif not @translations[$1][:places].include?(clean_filename + ":" + line.to_s)
        @translations[$1][:places] << clean_filename + ":" + line.to_s
      end
    end
  end

  def scan_twig_filter(text, line, filename, clean_filename)
    text.gsub(/[\s\{\(](#{STRING})\s*\|\s*translate(?!_plural)#{@twig_domain}\s*\|\s*format\(.*?\).*?/) do
      if @translations[$1].nil?
        @translations[$1] = { :places => [clean_filename + ":" + line.to_s],
                              :filter => true,
                              :plural => false }
      elsif not @translations[$1][:places].include?(clean_filename + ":" + line.to_s)
        @translations[$1][:places] << clean_filename + ":" + line.to_s
      end
    end
  end

  def scan_twig_plural(text, line, filename, clean_filename)
    text.gsub(/[\s\{\(](#{STRING})\s*\|\s*translate_plural\((#{STRING}),.*?#{@twig_arg_domain}\)\s*\|\s*format\(.*?\)/) do
      if @translations[$1].nil?
        @translations[$1] = { :places => [clean_filename + ":" + line.to_s],
                              :filter => true,
                              :plural => $4 }
      elsif not @translations[$1][:places].include?(clean_filename + ":" + line.to_s)
        @translations[$1][:places] << clean_filename + ":" + line.to_s
      end
    end
  end

  def print_pofile
    puts '# '+OPTIONS[:project]+' Translation File.'
    puts '# Copyright (C) '+Time.now.utc.strftime("%Y")+' '+OPTIONS[:maintainer].gsub(/ <([^>]+)>/, "")
    puts '# This file is distributed under the same license as the '+OPTIONS[:project]+' package.'
    puts '# FIRST AUTHOR <EMAIL@ADDRESS>, YEAR.'
    puts '#'
    puts '#, fuzzy'
    puts 'msgid ""'
    puts 'msgstr ""'
    puts '"Project-Id-Version: '+OPTIONS[:project]+'\n"'
    puts '"Report-Msgid-Bugs-To: '+OPTIONS[:maintainer].gsub(/[^<]+ <([^>]+)>/, "\\1")+'\n"'
    puts '"POT-Creation-Date: '+Time.now.utc.strftime("%Y-%m-%d %H:%M")+'+0000\n"'
    puts '"PO-Revision-Date: YEAR-MO-DA HO:MI+ZONE\n"'
    puts '"Last-Translator: FIRST LAST <EMAIL@EXAMPLE.COM>\n"'
    puts '"Language-Team: LANGUAGE <EMAIL@EXAMPLE.COM>\n"'
    puts '"MIME-Version: 1.0\n"'
    puts '"Content-Type: text/plain; charset=UTF-8\n"'
    puts '"Content-Transfer-Encoding: 8bit\n"'
    puts '"Plural-Forms: nplurals=INTEGER; plural=EXPRESSION;\n"'
    puts ''

    output = ""
    @translations.each do |text, attr|
      attr[:places].each do |place|
        output << "#: "+place+"\n"
      end
      output << "#, php-format\n" if attr[:filter]
      output << "msgid "+text+"\n"
      output << "msgid_plural "+attr[:plural]+"\n" if attr[:plural]

      if attr[:plural]
        output << "msgstr[0] \"#{OPTIONS[:msgstr]}\"\n"
        output << "msgstr[1] \"#{OPTIONS[:msgstr]}\"\n"
      else
        output << "msgstr \"#{(attr[:filter]) ? OPTIONS[:msgstr_filter] || OPTIONS[:msgstr] : OPTIONS[:msgstr]}\"\n"
      end

      output << "\n"
    end
    puts output
  end
end

Gettext.new ARGV[0] || "."
