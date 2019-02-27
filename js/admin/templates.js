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
templates['stun-server'] = template({"compiler":[7,">= 4.0.0"],"main":function(container,depth0,helpers,partials,data) {
    var helper, alias1=depth0 != null ? depth0 : (container.nullContext || {}), alias2=helpers.helperMissing, alias3="function", alias4=container.escapeExpression;

  return "<div class=\"stun-server\">\n	<input type=\"text\" name=\"stun_server\" placeholder=\"stunserver:port\" value=\""
    + alias4(((helper = (helper = helpers.server || (depth0 != null ? depth0.server : depth0)) != null ? helper : alias2),(typeof helper === alias3 ? helper.call(alias1,{"name":"server","hash":{},"data":data}) : helper)))
    + "\" aria-label=\""
    + alias4(((helper = (helper = helpers.stunTXT || (depth0 != null ? depth0.stunTXT : depth0)) != null ? helper : alias2),(typeof helper === alias3 ? helper.call(alias1,{"name":"stunTXT","hash":{},"data":data}) : helper)))
    + "\" />\n	<a class=\"icon icon-delete\" title=\""
    + alias4(((helper = (helper = helpers.deleteTXT || (depth0 != null ? depth0.deleteTXT : depth0)) != null ? helper : alias2),(typeof helper === alias3 ? helper.call(alias1,{"name":"deleteTXT","hash":{},"data":data}) : helper)))
    + "\"></a>\n	<a class=\"icon icon-add\" title=\""
    + alias4(((helper = (helper = helpers.newTXT || (depth0 != null ? depth0.newTXT : depth0)) != null ? helper : alias2),(typeof helper === alias3 ? helper.call(alias1,{"name":"newTXT","hash":{},"data":data}) : helper)))
    + "\"></a>\n	<span class=\"icon icon-checkmark-color hidden\" title=\""
    + alias4(((helper = (helper = helpers.savedTXT || (depth0 != null ? depth0.savedTXT : depth0)) != null ? helper : alias2),(typeof helper === alias3 ? helper.call(alias1,{"name":"savedTXT","hash":{},"data":data}) : helper)))
    + "\"></span>\n</div>\n";
},"useData":true});
templates['turn-server'] = template({"1":function(container,depth0,helpers,partials,data) {
    var helper, alias1=depth0 != null ? depth0 : (container.nullContext || {}), alias2=helpers.helperMissing, alias3="function", alias4=container.escapeExpression;

  return "				<option value=\"udp,tcp\">"
    + alias4(((helper = (helper = helpers.UDPTCPTXT || (depth0 != null ? depth0.UDPTCPTXT : depth0)) != null ? helper : alias2),(typeof helper === alias3 ? helper.call(alias1,{"name":"UDPTCPTXT","hash":{},"data":data}) : helper)))
    + "</option>\n				<option value=\"udp\">"
    + alias4(((helper = (helper = helpers.UDPTXT || (depth0 != null ? depth0.UDPTXT : depth0)) != null ? helper : alias2),(typeof helper === alias3 ? helper.call(alias1,{"name":"UDPTXT","hash":{},"data":data}) : helper)))
    + "</option>\n				<option value=\"tcp\">"
    + alias4(((helper = (helper = helpers.TCPTXT || (depth0 != null ? depth0.TCPTXT : depth0)) != null ? helper : alias2),(typeof helper === alias3 ? helper.call(alias1,{"name":"TCPTXT","hash":{},"data":data}) : helper)))
    + "</option>\n";
},"compiler":[7,">= 4.0.0"],"main":function(container,depth0,helpers,partials,data) {
    var stack1, helper, alias1=depth0 != null ? depth0 : (container.nullContext || {}), alias2=helpers.helperMissing, alias3="function", alias4=container.escapeExpression;

  return "<div class=\"turn-server\">\n	<input type=\"text\" class=\"server\" placeholder=\"turn.example.org\" value=\""
    + alias4(((helper = (helper = helpers.server || (depth0 != null ? depth0.server : depth0)) != null ? helper : alias2),(typeof helper === alias3 ? helper.call(alias1,{"name":"server","hash":{},"data":data}) : helper)))
    + "\" aria-label=\""
    + alias4(((helper = (helper = helpers.turnTXT || (depth0 != null ? depth0.turnTXT : depth0)) != null ? helper : alias2),(typeof helper === alias3 ? helper.call(alias1,{"name":"turnTXT","hash":{},"data":data}) : helper)))
    + "\">\n	<input type=\"text\" class=\"secret\" placeholder=\""
    + alias4(((helper = (helper = helpers.sharedSecretTXT || (depth0 != null ? depth0.sharedSecretTXT : depth0)) != null ? helper : alias2),(typeof helper === alias3 ? helper.call(alias1,{"name":"sharedSecretTXT","hash":{},"data":data}) : helper)))
    + "\" value=\""
    + alias4(((helper = (helper = helpers.secret || (depth0 != null ? depth0.secret : depth0)) != null ? helper : alias2),(typeof helper === alias3 ? helper.call(alias1,{"name":"secret","hash":{},"data":data}) : helper)))
    + "\" aria-label=\""
    + alias4(((helper = (helper = helpers.sharedSecretDescTXT || (depth0 != null ? depth0.sharedSecretDescTXT : depth0)) != null ? helper : alias2),(typeof helper === alias3 ? helper.call(alias1,{"name":"sharedSecretDescTXT","hash":{},"data":data}) : helper)))
    + "\">\n	<select class=\"protocols\" title=\""
    + alias4(((helper = (helper = helpers.protocolsTXT || (depth0 != null ? depth0.protocolsTXT : depth0)) != null ? helper : alias2),(typeof helper === alias3 ? helper.call(alias1,{"name":"protocolsTXT","hash":{},"data":data}) : helper)))
    + "\">\n"
    + ((stack1 = (helpers.select || (depth0 && depth0.select) || alias2).call(alias1,(depth0 != null ? depth0.protocols : depth0),{"name":"select","hash":{},"fn":container.program(1, data, 0),"inverse":container.noop,"data":data})) != null ? stack1 : "")
    + "	</select>\n	<a class=\"icon icon-category-monitoring\" title=\""
    + alias4(((helper = (helper = helpers.testTXT || (depth0 != null ? depth0.testTXT : depth0)) != null ? helper : alias2),(typeof helper === alias3 ? helper.call(alias1,{"name":"testTXT","hash":{},"data":data}) : helper)))
    + "\"></a>\n	<a class=\"icon icon-delete\" title=\""
    + alias4(((helper = (helper = helpers.deleteTXT || (depth0 != null ? depth0.deleteTXT : depth0)) != null ? helper : alias2),(typeof helper === alias3 ? helper.call(alias1,{"name":"deleteTXT","hash":{},"data":data}) : helper)))
    + "\"></a>\n	<a class=\"icon icon-add\" title=\""
    + alias4(((helper = (helper = helpers.newTXT || (depth0 != null ? depth0.newTXT : depth0)) != null ? helper : alias2),(typeof helper === alias3 ? helper.call(alias1,{"name":"newTXT","hash":{},"data":data}) : helper)))
    + "\"></a>\n	<span class=\"icon icon-checkmark-color hidden\" title=\""
    + alias4(((helper = (helper = helpers.savedTXT || (depth0 != null ? depth0.savedTXT : depth0)) != null ? helper : alias2),(typeof helper === alias3 ? helper.call(alias1,{"name":"savedTXT","hash":{},"data":data}) : helper)))
    + "\"></span>\n</div>\n";
},"useData":true});
})();