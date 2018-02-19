/* <![CDATA[  */
$(document).ready(function ()
{
  $("a[id^='linkreport\:'").on( "click", function() {

      var idref = $(this).attr('id');
      var tmp = idref.split(':');

      $.post("index.php", { report: tmp[1] },
      function(data) {
        $("#reportdata").dialog({
          autoOpen: true,
          show: { effect: 'Fade', duration: 1000 },
          hide: { effect: 'Fade', duration: 4000 },
          resizable: true,
          height: 'auto',
          modal: false,
          width: 'auto',
          title: "Report data",
          close: function( event, ui ) {}
        }).html(data);
      });

  });

  $("#id_domain").change(function() { reloadSearch(); });
  $("#id_org").change(function() { reloadSearch(); });
  $("#id_action_reset").bind("click", function() { window.location.href = baseurl; } )
});

var baseurl = location.protocol + "//" + location.hostname + "/";

  function reloadSearch()
  {
    //var baseurl = location.protocol + "//" + location.hostname + "/";
    var domainChoose = $('#id_domain option:selected').val();
    var organizationChoose = $('#id_org option:selected').val();
    var url = "?";
    if (domainChoose != "all")
    {
      url += "domain=" + domainChoose;
    }
    if (organizationChoose != "all")
    {
      if (domainChoose != 'all')
      {
        url += "&";
      }
      url += "org=" + organizationChoose;
    }
    window.location.href  = baseurl + url;
  }
/* ]]> */
