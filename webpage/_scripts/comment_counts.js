(function($) {
  $(document).ready(function() {
    var url_elements = $("span[data-href]");
    var urls = url_elements.map(function(index, elem) { return $(elem).data('href'); }).toArray();
    var app_key = $('meta[property=fb\\:app_id]').attr('content');

    var queries = {index_link_status_url: 'select url,commentsbox_count from link_stat where url in ("' + urls.join('","') + '")'};
    data = {
      api_key: app_key,
      format: "json-strings",
      method: "fql.multiquery",
      pretty: "0",
      queries: JSON.stringify(queries),
      sdk: "joey"
    };
    $.getJSON("//api-read.facebook.com/restserver.php", data, function(data) {
      var results = data[0]['fql_result_set'];
      var fb_tags = {};
      url_elements.each(function(index, elem) {
        var jElem = $(elem);
        fb_tags[jElem.data('href')] = jElem;
      });
      $(results).each(function(index, elem) {
        fb_tags[elem.url].text(elem.commentsbox_count);
        if (parseInt(elem.commentsbox_count) == 1) {
          fb_tags[elem.url].parent().find("span.fb_comments_text").text("comment");
        }
      });
    });
  });
}(jQuery));
