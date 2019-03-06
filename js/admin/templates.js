(function() {
  var template = Handlebars.template, templates = OCA.VideoCalls.Admin.Templates = OCA.VideoCalls.Admin.Templates || {};
templates['signaling-server'] = template({"1":function(container,depth0,helpers,partials,data) {
    return " checked=\"checked\"";
},"compiler":[7,">= 4.0.0"],"main":function(container,depth0,helpers,partials,data) {
    var stack1, helper, alias1=depth0 != null ? depth0 : (container.nullContext || {}), alias2=helpers.helperMissing, alias3="function", alias4=container.escapeExpression;

  return "<div class=\"signaling-server\">\n	<input type=\"text\" class=\"server\" placeholder=\"wss://signaling.example.org\" value=\""
    + alias4(((helper = (helper = helpers.server || (depth0 != null ? depth0.server : depth0)) != null ? helper : alias2),(typeof helper === alias3 ? helper.call(alias1,{"name":"server","hash":{},"data":data}) : helper)))
    + "\" aria-label=\""
    + alias4(((helper = (helper = helpers.signalingServerURLTXT || (depth0 != null ? depth0.signalingServerURLTXT : depth0)) != null ? helper : alias2),(typeof helper === alias3 ? helper.call(alias1,{"name":"signalingServerURLTXT","hash":{},"data":data}) : helper)))
    + "\">\n	<input type=\"checkbox\" id=\"verify"
    + alias4(((helper = (helper = helpers.seed || (depth0 != null ? depth0.seed : depth0)) != null ? helper : alias2),(typeof helper === alias3 ? helper.call(alias1,{"name":"seed","hash":{},"data":data}) : helper)))
    + "\" name=\"verify"
    + alias4(((helper = (helper = helpers.seed || (depth0 != null ? depth0.seed : depth0)) != null ? helper : alias2),(typeof helper === alias3 ? helper.call(alias1,{"name":"seed","hash":{},"data":data}) : helper)))
    + "\" class=\"checkbox verify\" value=\"1\" "
    + ((stack1 = helpers["if"].call(alias1,(depth0 != null ? depth0.verify : depth0),{"name":"if","hash":{},"fn":container.program(1, data, 0),"inverse":container.noop,"data":data})) != null ? stack1 : "")
    + ">\n	<label for=\"verify"
    + alias4(((helper = (helper = helpers.seed || (depth0 != null ? depth0.seed : depth0)) != null ? helper : alias2),(typeof helper === alias3 ? helper.call(alias1,{"name":"seed","hash":{},"data":data}) : helper)))
    + "\">"
    + alias4(((helper = (helper = helpers.validatingSSLTXT || (depth0 != null ? depth0.validatingSSLTXT : depth0)) != null ? helper : alias2),(typeof helper === alias3 ? helper.call(alias1,{"name":"validatingSSLTXT","hash":{},"data":data}) : helper)))
    + "</label>\n	<a class=\"icon icon-delete\" title=\""
    + alias4(((helper = (helper = helpers.deleteTXT || (depth0 != null ? depth0.deleteTXT : depth0)) != null ? helper : alias2),(typeof helper === alias3 ? helper.call(alias1,{"name":"deleteTXT","hash":{},"data":data}) : helper)))
    + "\"></a>\n	<a class=\"icon icon-add\" title=\""
    + alias4(((helper = (helper = helpers.addNewTXT || (depth0 != null ? depth0.addNewTXT : depth0)) != null ? helper : alias2),(typeof helper === alias3 ? helper.call(alias1,{"name":"addNewTXT","hash":{},"data":data}) : helper)))
    + "\"></a>\n	<span class=\"icon icon-checkmark-color hidden\" title=\""
    + alias4(((helper = (helper = helpers.savedTXT || (depth0 != null ? depth0.savedTXT : depth0)) != null ? helper : alias2),(typeof helper === alias3 ? helper.call(alias1,{"name":"savedTXT","hash":{},"data":data}) : helper)))
    + "\"></span>\n</div>\n";
},"useData":true});
})();