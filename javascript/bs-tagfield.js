(function(d){
  function addScript(s){
    var js, id = s.replace(/[^0-9a-z]/gi, ''); if (d.getElementById(id)) {return;}
    js = d.createElement('script'); js.id = id; js.async = false;
    js.src = "_resources/strap-tagfield/javascript/" + s;
    d.getElementsByTagName('body')[0].appendChild(js);
  }
  d.addEventListener("DOMContentLoaded", () => {
    addScript('typeahead.js');
    addScript('bootstrap-tagfield.js');
    addScript('bootstrap-tagfield-init.js');
  });
}(document));