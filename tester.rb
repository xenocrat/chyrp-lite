require "test/unit"
require "net/http"
require "rubygems"
require "hpricot"
require "yaml"

open("includes/config.yaml.php") do |config_file|
  CONFIG = YAML::load(config_file.read.sub(%Q{<?php header("Status: 403"); exit("Access denied."); ?>\n}, ''))
end

`rm -Rfv uploads/*`
`mysql -f --user=root -D chyrp -e 'TRUNCATE TABLE posts; TRUNCATE TABLE post_attributes; TRUNCATE TABLE pages; TRUNCATE TABLE comments;'`

FUZZER = {
  :textarea => "Lorem ipsum dolor sit amet, consectetuer adipiscing elit. Nullam urna. Vivamus nisl. Mauris iaculis rutrum elit. 
                Cras ornare congue mi. Nullam mi quam, luctus dapibus, euismod ut, dapibus sed, dui. 
                Praesent est lectus, rutrum ac, blandit vitae, hendrerit at, massa. 
                Morbi mauris purus, lobortis vel, commodo vitae, aliquet vehicula, ante. 
                Nunc commodo. Pellentesque vel lacus. Quisque eros. Maecenas et quam. Curabitur eget justo a ante dignissim dapibus. 
                Sed et lacus. Suspendisse potenti. Vivamus ipsum mi, blandit vitae, scelerisque a, pellentesque vitae, nisl. 
                Donec vitae est et est egestas laoreet. Vestibulum commodo elit ut nisl. Nullam volutpat nisi non elit. 
                Morbi sapien eros, ornare et, dapibus id, mattis id, nibh. Suspendisse ut nisl id est scelerisque faucibus.
                \n\nPraesent viverra felis nec justo. Duis gravida tempor massa. Aliquam lobortis tortor eu purus. 
                Phasellus volutpat, justo eget molestie rhoncus, nibh tortor suscipit justo, non vehicula tortor tortor id sapien. 
                Vivamus quis nisl et neque ullamcorper viverra. Vestibulum accumsan, elit luctus auctor fermentum, 
                lorem tellus dignissim odio, a lobortis magna nulla eget arcu. 
                Phasellus vel erat at dolor sagittis luctus. Nulla facilisi. In eros eros, molestie sit amet, ornare a, fermentum et, dui. 
                Vivamus vel turpis quis diam iaculis dapibus. Nunc lacinia. Integer commodo, urna interdum imperdiet pretium, 
                libero nulla pellentesque turpis, in ultrices neque tortor at arcu. Sed mollis odio eget mauris ultricies bibendum. 
                Vivamus malesuada metus vel arcu. Nam sit amet metus. Pellentesque quis felis non nibh adipiscing adipiscing.",
  :text => "Test Input"
}

SERVER = Net::HTTP.new "localhost"

CHYRP = "/chyrp"

URL = "http://localhost/chyrp"

HEADERS = {
  "Cookie" => "ChyrpSession=e5f9cd17ab00580649ce618791b5675a", # NOTE: This has to be changed to keep in sync with your browser.
  "User-Agent" => "tester.rb"
}

POSTS = {
  :text => {
    "title" => "Test Text Post",
    "body" => FUZZER[:textarea]
  },
  :quote => {
    "quote" => FUZZER[:textarea].split(". ")[0] + ".",
    "source" => "Chyrp Tester"
  },
  :chat => {
    "title" => "Test Chat Post",
    "dialogue" => "Person: Hi!\nMe (me): Hello!\nPerson: How are you?\nMe: Great, thanks! And you?\nPerson: FUCKING AWESOME."
  },
  :link => {
    "name" => "Google Search",
    "source" => "http://google.com/",
    "description" => "I can't believe how long I've gone without finding this site."
  }
}

class Chyrp < Test::Unit::TestCase
  def test_add_post
    POSTS.each do |feather, attrs|
      resp, write = get "/admin/?action=write_post&feather="+ feather.to_s

      page, data = Hpricot(write), attrs.clone

      form_fuzz(page/"form").each do |key, val|
        data[key] = val unless data.has_key? key
      end

      data['feather'] = feather.to_s
      data['hash'] = CONFIG['secure_hashkey']

      post (page/"form").attr("action"), data
    end
  end

  def test_add_draft
    POSTS.each do |feather, attrs|
      resp, write = get "/admin/?action=write_post&feather="+ feather.to_s

      page, data = Hpricot(write), attrs.clone

      form_fuzz(page/"form").each do |key, val|
        data[key] = val unless data.has_key? key
      end

      data['feather'] = feather.to_s
      data['draft'] = "true"
      data['status'] = "draft"
      data['hash'] = CONFIG['secure_hashkey']

      post (page/"form").attr("action"), data
    end
  end

  def test_view_post
    resp, page = test_index
    page = Hpricot(page)
    return unless page.to_s =~ /class="post /

    post_url = (page/".post:first/h2/a").attr("href").sub(URL, "")

    get post_url
  end

  def test_add_page
    resp, write = get "/admin/?action=write_page"

    page = Hpricot(write)
    data = form_fuzz(page/"form")
    data['hash'] = CONFIG['secure_hashkey']

    post (page/"form").attr("action"), data
  end

  def test_view_page
    resp, page = test_index
    page = Hpricot(page)

    first_page = (page/"#sidebar/ul:nth(0)/li:nth(0)/a")
    return unless first_page

    page_url = first_page.attr("href").sub(URL, "")

    get page_url
  end

  def test_general_settings
    resp, page = get "/admin/?action=general_settings"
    page = Hpricot(page)

    settings = form_get(page/"form")
    settings['description'].reverse!

    post (page/"form").attr("action"), settings
  end

  def test_content_settings
    resp, page = get"/admin/?action=content_settings"
    page = Hpricot(page)

    post (page/"form").attr("action"), form_get(page/"form")
  end

  def test_user_settings
    resp, page = get "/admin/?action=user_settings"
    page = Hpricot(page)

    post (page/"form").attr("action"), form_get(page/"form")
  end

  def test_route_settings
    resp, page = get "/admin/?action=route_settings"
    page = Hpricot(page)

    post (page/"form").attr("action"), form_get(page/"form")
  end

  def test_archive
    get "/archive/"
  end

  def test_archive_year
    get "/archive/"+ Time.now.strftime("%y/")
  end

  def test_archive_month
    get "/archive/"+ Time.now.strftime("%y/%m/")
  end

  def test_archive_day
    get "/archive/"+ Time.now.strftime("%y/%m/%d/")
  end

  def test_index
    get "/"
  end

  def test_feed
    get "/feed/"
  end

  def test_search
    resp, page = get("/search/Lorem+ipsum/")
    assert_no_match /No Results/, page, "No search results listed."
  end

  def test_pagination
    resp, page = get("/page/2/")
    return unless page =~ /class="post /
    assert_match /Page 2 of /, page, "No pagination links displayed."
  end

  def test_drafts
    resp, page = get("/drafts/")
    assert_no_match /No Drafts/, page, "No draft posts listed."
  end

  def test_404
    assert (error?(get("/rghtueighntrwiu5ytn5ueygn/", false)) == "404 Not Found"), "Fuzzed dirty route was not a 404."
  end

  def test_dirty_404
    assert (error?(get("/?action=rghtueighntrwiu5ytn5ueygn", false)) == "404 Not Found"), "Fuzzed dirty route was not a 404."
  end

  private
    def get url, test = true
      url = CHYRP + url if url[0] == 47 # /

      receive = SERVER.get(url, HEADERS)

      if test
        error = error? receive
        assert (not error), error
      end

      receive
    end

    def post url, data, test = true
      send = []
      data.each do |key, val|
        send << key +"="+ val
      end

      url = CHYRP + url if url[0] == 47 # /

      send = SERVER.post(url, send.join("&"), HEADERS)

      if test
        error = error? send
        assert (not error), error
      end

      send
    end

    def form_fuzz form
      data = form_get form

      (form/"*[@name]").each do |field|
        next unless data[field['name']].nil? or data[field['name']].empty?

        if field.name == "textarea"
          data[field['name']] = FUZZER[:textarea]
        elsif field.name == "input"
          next if field['type'] != "text" or field['name'] == "trackbacks" or field['name'] == "slug"
          data[field['name']] = FUZZER[:text]
        end
      end

      data
    end

    def form_get form
      data = {}

      (form/"*[@name]").each do |field|
        if field.name == "select"
          selected = (field/"option[@selected]")
          option = selected.length > 0 ? selected : (field/"option:nth(0)")
          data[field['name']] = option.attr("value")
        elsif field.name != "button"
          if field['type'] == "checkbox"
            data[field['name']] = (field['checked'] || "no") == "checked" ? "1" : "0"
          else
            data[field['name']] = field['value'] || field.html
          end
        end
      end

      data
    end

    def error? response
      status, page = response

      page.gsub /^ERROR: (.+)/ do
        return $1
      end

      return "404 Not Found" if status.class == Net::HTTPNotFound

      false
    end

    def success? response
      status, page = response
      result =~ /^SUCCESS: (.+)/
    end
end
