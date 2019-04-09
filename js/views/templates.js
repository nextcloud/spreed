(function() {
  var template = Handlebars.template, templates = OCA.Talk.Views.Templates = OCA.Talk.Views.Templates || {};
templates['callbutton'] = template({"1":function(container,depth0,helpers,partials,data) {
    var helper;

  return "	<button class=\"leave-call primary\">"
    + container.escapeExpression(((helper = (helper = helpers.leaveCallText || (depth0 != null ? depth0.leaveCallText : depth0)) != null ? helper : helpers.helperMissing),(typeof helper === "function" ? helper.call(depth0 != null ? depth0 : (container.nullContext || {}),{"name":"leaveCallText","hash":{},"data":data}) : helper)))
    + "<span class=\"icon icon-loading-small hidden\"></span></button>\n";
},"3":function(container,depth0,helpers,partials,data) {
    var stack1;

  return ((stack1 = helpers["if"].call(depth0 != null ? depth0 : (container.nullContext || {}),(depth0 != null ? depth0.isReadOnly : depth0),{"name":"if","hash":{},"fn":container.program(4, data, 0),"inverse":container.program(6, data, 0),"data":data})) != null ? stack1 : "");
},"4":function(container,depth0,helpers,partials,data) {
    var helper, alias1=depth0 != null ? depth0 : (container.nullContext || {}), alias2=helpers.helperMissing, alias3="function", alias4=container.escapeExpression;

  return "		<button class=\"join-call primary has-tooltip\" title=\""
    + alias4(((helper = (helper = helpers.readOnlyText || (depth0 != null ? depth0.readOnlyText : depth0)) != null ? helper : alias2),(typeof helper === alias3 ? helper.call(alias1,{"name":"readOnlyText","hash":{},"data":data}) : helper)))
    + "\" disabled=\"\">"
    + alias4(((helper = (helper = helpers.startCallText || (depth0 != null ? depth0.startCallText : depth0)) != null ? helper : alias2),(typeof helper === alias3 ? helper.call(alias1,{"name":"startCallText","hash":{},"data":data}) : helper)))
    + "</button>\n";
},"6":function(container,depth0,helpers,partials,data) {
    var stack1;

  return ((stack1 = helpers["if"].call(depth0 != null ? depth0 : (container.nullContext || {}),(depth0 != null ? depth0.hasCall : depth0),{"name":"if","hash":{},"fn":container.program(7, data, 0),"inverse":container.program(9, data, 0),"data":data})) != null ? stack1 : "");
},"7":function(container,depth0,helpers,partials,data) {
    var helper;

  return "			<button class=\"join-call call-ongoing primary\">"
    + container.escapeExpression(((helper = (helper = helpers.joinCallText || (depth0 != null ? depth0.joinCallText : depth0)) != null ? helper : helpers.helperMissing),(typeof helper === "function" ? helper.call(depth0 != null ? depth0 : (container.nullContext || {}),{"name":"joinCallText","hash":{},"data":data}) : helper)))
    + "<span class=\"icon icon-loading-small hidden\"></span></button>\n";
},"9":function(container,depth0,helpers,partials,data) {
    var helper;

  return "			<button class=\"join-call primary\">"
    + container.escapeExpression(((helper = (helper = helpers.startCallText || (depth0 != null ? depth0.startCallText : depth0)) != null ? helper : helpers.helperMissing),(typeof helper === "function" ? helper.call(depth0 != null ? depth0 : (container.nullContext || {}),{"name":"startCallText","hash":{},"data":data}) : helper)))
    + "<span class=\"icon icon-loading-small hidden\"></span></button>\n";
},"compiler":[7,">= 4.0.0"],"main":function(container,depth0,helpers,partials,data) {
    var stack1;

  return ((stack1 = helpers["if"].call(depth0 != null ? depth0 : (container.nullContext || {}),(depth0 != null ? depth0.isInCall : depth0),{"name":"if","hash":{},"fn":container.program(1, data, 0),"inverse":container.program(3, data, 0),"data":data})) != null ? stack1 : "");
},"useData":true});
templates['callinfoview'] = template({"1":function(container,depth0,helpers,partials,data) {
    var helper, alias1=depth0 != null ? depth0 : (container.nullContext || {}), alias2=helpers.helperMissing, alias3="function", alias4=container.escapeExpression;

  return "	<a class=\"file-link\" href=\""
    + alias4(((helper = (helper = helpers.fileLink || (depth0 != null ? depth0.fileLink : depth0)) != null ? helper : alias2),(typeof helper === alias3 ? helper.call(alias1,{"name":"fileLink","hash":{},"data":data}) : helper)))
    + "\" target=\"_blank\" rel=\"noopener noreferrer\" data-original-title=\""
    + alias4(((helper = (helper = helpers.fileLinkTitle || (depth0 != null ? depth0.fileLinkTitle : depth0)) != null ? helper : alias2),(typeof helper === alias3 ? helper.call(alias1,{"name":"fileLinkTitle","hash":{},"data":data}) : helper)))
    + "\">\n		<span class=\"icon icon-file\"></span>\n	</a>\n";
},"3":function(container,depth0,helpers,partials,data) {
    var stack1, alias1=depth0 != null ? depth0 : (container.nullContext || {});

  return "	<div class=\"share-link-options\">\n"
    + ((stack1 = helpers["if"].call(alias1,(depth0 != null ? depth0.canFullModerate : depth0),{"name":"if","hash":{},"fn":container.program(4, data, 0),"inverse":container.noop,"data":data})) != null ? stack1 : "")
    + ((stack1 = helpers["if"].call(alias1,(depth0 != null ? depth0.isPublic : depth0),{"name":"if","hash":{},"fn":container.program(7, data, 0),"inverse":container.noop,"data":data})) != null ? stack1 : "")
    + "	</div>\n";
},"4":function(container,depth0,helpers,partials,data) {
    var stack1, helper, alias1=depth0 != null ? depth0 : (container.nullContext || {});

  return "		<input name=\"link-checkbox\" id=\"link-checkbox\" class=\"checkbox link-checkbox\" value=\"1\" "
    + ((stack1 = helpers["if"].call(alias1,(depth0 != null ? depth0.isPublic : depth0),{"name":"if","hash":{},"fn":container.program(5, data, 0),"inverse":container.noop,"data":data})) != null ? stack1 : "")
    + " type=\"checkbox\">\n		<label for=\"link-checkbox\" class=\"link-checkbox-label\">"
    + container.escapeExpression(((helper = (helper = helpers.linkCheckboxLabel || (depth0 != null ? depth0.linkCheckboxLabel : depth0)) != null ? helper : helpers.helperMissing),(typeof helper === "function" ? helper.call(alias1,{"name":"linkCheckboxLabel","hash":{},"data":data}) : helper)))
    + "</label>\n";
},"5":function(container,depth0,helpers,partials,data) {
    return " checked=\"checked\"";
},"7":function(container,depth0,helpers,partials,data) {
    var stack1, helper, alias1=depth0 != null ? depth0 : (container.nullContext || {});

  return "		<div class=\"clipboard-button\"><span class=\"button icon-clippy\"></span></div>\n		<div class=\"password-button\">\n			<span class=\"button "
    + ((stack1 = helpers["if"].call(alias1,(depth0 != null ? depth0.hasPassword : depth0),{"name":"if","hash":{},"fn":container.program(8, data, 0),"inverse":container.program(10, data, 0),"data":data})) != null ? stack1 : "")
    + "\"></span>\n			<div class=\"popovermenu password-menu menu-right\">\n				<ul>\n					<li>\n						<span class=\"menuitem "
    + ((stack1 = helpers["if"].call(alias1,(depth0 != null ? depth0.hasPassword : depth0),{"name":"if","hash":{},"fn":container.program(8, data, 0),"inverse":container.program(10, data, 0),"data":data})) != null ? stack1 : "")
    + " password-option\">\n							<form class=\"password-form\">\n								<input class=\"password-input\" required maxlength=\"200\" type=\"password\"\n									placeholder=\""
    + container.escapeExpression(((helper = (helper = helpers.passwordInputPlaceholder || (depth0 != null ? depth0.passwordInputPlaceholder : depth0)) != null ? helper : helpers.helperMissing),(typeof helper === "function" ? helper.call(alias1,{"name":"passwordInputPlaceholder","hash":{},"data":data}) : helper)))
    + "\">\n								<input type=\"submit\" value=\"\" autocomplete=\"new-password\" class=\"icon icon-confirm password-confirm\"></input>\n							</form>\n						</span>\n					</li>\n				</ul>\n			</div>\n		</div>\n";
},"8":function(container,depth0,helpers,partials,data) {
    return "icon-password";
},"10":function(container,depth0,helpers,partials,data) {
    return "icon-no-password";
},"12":function(container,depth0,helpers,partials,data) {
    return "	<div class=\"share-link-options\">\n		<div class=\"clipboard-button\"><span class=\"button icon-clippy\"></span></div>\n	</div>\n";
},"compiler":[7,">= 4.0.0"],"main":function(container,depth0,helpers,partials,data) {
    var stack1, alias1=depth0 != null ? depth0 : (container.nullContext || {});

  return "<div class=\"room-name-container\">\n	<div class=\"room-name\"></div>\n"
    + ((stack1 = helpers["if"].call(alias1,(depth0 != null ? depth0.isRoomForFile : depth0),{"name":"if","hash":{},"fn":container.program(1, data, 0),"inverse":container.noop,"data":data})) != null ? stack1 : "")
    + "</div>\n<div class=\"call-controls-container\">\n	<div class=\"call-button\"></div>\n"
    + ((stack1 = helpers["if"].call(alias1,(depth0 != null ? depth0.canModerate : depth0),{"name":"if","hash":{},"fn":container.program(3, data, 0),"inverse":container.noop,"data":data})) != null ? stack1 : "")
    + ((stack1 = helpers["if"].call(alias1,(depth0 != null ? depth0.showShareLink : depth0),{"name":"if","hash":{},"fn":container.program(12, data, 0),"inverse":container.noop,"data":data})) != null ? stack1 : "")
    + "</div>\n";
},"useData":true});
templates['chatview'] = template({"compiler":[7,">= 4.0.0"],"main":function(container,depth0,helpers,partials,data) {
    var helper;

  return "<ul class=\"comments\"></ul>\n<div class=\"emptycontent\">\n	<div class=\"icon-comment\"></div>\n	<p>"
    + container.escapeExpression(((helper = (helper = helpers.emptyResultLabel || (depth0 != null ? depth0.emptyResultLabel : depth0)) != null ? helper : helpers.helperMissing),(typeof helper === "function" ? helper.call(depth0 != null ? depth0 : (container.nullContext || {}),{"name":"emptyResultLabel","hash":{},"data":data}) : helper)))
    + "</p>\n</div>\n<div class=\"loading hidden\" style=\"height: 50px\"></div>\n";
},"useData":true});
templates['chatview_add_comment'] = template({"1":function(container,depth0,helpers,partials,data) {
    var helper;

  return "		<div class=\"author\">"
    + container.escapeExpression(((helper = (helper = helpers.actorDisplayName || (depth0 != null ? depth0.actorDisplayName : depth0)) != null ? helper : helpers.helperMissing),(typeof helper === "function" ? helper.call(depth0 != null ? depth0 : (container.nullContext || {}),{"name":"actorDisplayName","hash":{},"data":data}) : helper)))
    + "</div>\n";
},"3":function(container,depth0,helpers,partials,data) {
    return "		<div class=\"guest-name\"></div>\n";
},"5":function(container,depth0,helpers,partials,data) {
    return " with-add-button";
},"7":function(container,depth0,helpers,partials,data) {
    return "false";
},"9":function(container,depth0,helpers,partials,data) {
    return "true";
},"11":function(container,depth0,helpers,partials,data) {
    return "disabled=\"\"";
},"13":function(container,depth0,helpers,partials,data) {
    var helper;

  return "		<button class=\"share icon-add has-tooltip\" title=\""
    + container.escapeExpression(((helper = (helper = helpers.shareText || (depth0 != null ? depth0.shareText : depth0)) != null ? helper : helpers.helperMissing),(typeof helper === "function" ? helper.call(depth0 != null ? depth0 : (container.nullContext || {}),{"name":"shareText","hash":{},"data":data}) : helper)))
    + "\"></button>\n		<div class=\"shareLoading icon-loading-small hidden\"></div>\n";
},"compiler":[7,">= 4.0.0"],"main":function(container,depth0,helpers,partials,data) {
    var stack1, helper, alias1=depth0 != null ? depth0 : (container.nullContext || {}), alias2=helpers.helperMissing, alias3="function", alias4=container.escapeExpression;

  return "<div class=\"newCommentRow comment\">\n	<div class=\"authorRow currentUser\">\n		<div class=\"avatar\" data-user-id=\""
    + alias4(((helper = (helper = helpers.actorId || (depth0 != null ? depth0.actorId : depth0)) != null ? helper : alias2),(typeof helper === alias3 ? helper.call(alias1,{"name":"actorId","hash":{},"data":data}) : helper)))
    + "\"></div>\n"
    + ((stack1 = helpers["if"].call(alias1,(depth0 != null ? depth0.actorId : depth0),{"name":"if","hash":{},"fn":container.program(1, data, 0),"inverse":container.program(3, data, 0),"data":data})) != null ? stack1 : "")
    + "	</div>\n	<form class=\"newCommentForm"
    + ((stack1 = helpers["if"].call(alias1,(depth0 != null ? depth0.canShare : depth0),{"name":"if","hash":{},"fn":container.program(5, data, 0),"inverse":container.noop,"data":data})) != null ? stack1 : "")
    + "\">\n		<div contentEditable=\""
    + ((stack1 = helpers["if"].call(alias1,(depth0 != null ? depth0.isReadOnly : depth0),{"name":"if","hash":{},"fn":container.program(7, data, 0),"inverse":container.program(9, data, 0),"data":data})) != null ? stack1 : "")
    + "\" class=\"message\" data-placeholder=\""
    + alias4(((helper = (helper = helpers.newMessagePlaceholder || (depth0 != null ? depth0.newMessagePlaceholder : depth0)) != null ? helper : alias2),(typeof helper === alias3 ? helper.call(alias1,{"name":"newMessagePlaceholder","hash":{},"data":data}) : helper)))
    + "\">"
    + alias4(((helper = (helper = helpers.message || (depth0 != null ? depth0.message : depth0)) != null ? helper : alias2),(typeof helper === alias3 ? helper.call(alias1,{"name":"message","hash":{},"data":data}) : helper)))
    + "</div>\n		<input class=\"submit icon-confirm has-tooltip\" type=\"submit\" "
    + ((stack1 = helpers["if"].call(alias1,(depth0 != null ? depth0.isReadOnly : depth0),{"name":"if","hash":{},"fn":container.program(11, data, 0),"inverse":container.noop,"data":data})) != null ? stack1 : "")
    + " value=\"\" title=\""
    + alias4(((helper = (helper = helpers.submitText || (depth0 != null ? depth0.submitText : depth0)) != null ? helper : alias2),(typeof helper === alias3 ? helper.call(alias1,{"name":"submitText","hash":{},"data":data}) : helper)))
    + "\"/>\n		<div class=\"submitLoading icon-loading-small hidden\"></div>\n"
    + ((stack1 = helpers["if"].call(alias1,(depth0 != null ? depth0.canShare : depth0),{"name":"if","hash":{},"fn":container.program(13, data, 0),"inverse":container.noop,"data":data})) != null ? stack1 : "")
    + "	</form>\n</div>\n";
},"useData":true});
templates['chatview_comment'] = template({"1":function(container,depth0,helpers,partials,data) {
    return "";
},"3":function(container,depth0,helpers,partials,data) {
    return " systemMessage";
},"5":function(container,depth0,helpers,partials,data) {
    return " currentUser";
},"7":function(container,depth0,helpers,partials,data) {
    return " guestUser";
},"9":function(container,depth0,helpers,partials,data) {
    var stack1, helper, alias1=depth0 != null ? depth0 : (container.nullContext || {}), alias2=helpers.helperMissing, alias3="function", alias4=container.escapeExpression;

  return "		<div class=\"avatar\" data-user-id=\""
    + ((stack1 = helpers["if"].call(alias1,(depth0 != null ? depth0.isGuest : depth0),{"name":"if","hash":{},"fn":container.program(1, data, 0),"inverse":container.program(10, data, 0),"data":data})) != null ? stack1 : "")
    + "\" data-user-display-name=\""
    + alias4(((helper = (helper = helpers.actorDisplayName || (depth0 != null ? depth0.actorDisplayName : depth0)) != null ? helper : alias2),(typeof helper === alias3 ? helper.call(alias1,{"name":"actorDisplayName","hash":{},"data":data}) : helper)))
    + "\"></div>\n		<div class=\"author\">"
    + alias4(((helper = (helper = helpers.actorDisplayName || (depth0 != null ? depth0.actorDisplayName : depth0)) != null ? helper : alias2),(typeof helper === alias3 ? helper.call(alias1,{"name":"actorDisplayName","hash":{},"data":data}) : helper)))
    + "</div>\n";
},"10":function(container,depth0,helpers,partials,data) {
    var helper;

  return container.escapeExpression(((helper = (helper = helpers.actorId || (depth0 != null ? depth0.actorId : depth0)) != null ? helper : helpers.helperMissing),(typeof helper === "function" ? helper.call(depth0 != null ? depth0 : (container.nullContext || {}),{"name":"actorId","hash":{},"data":data}) : helper)));
},"12":function(container,depth0,helpers,partials,data) {
    return " live-relative-timestamp";
},"compiler":[7,">= 4.0.0"],"main":function(container,depth0,helpers,partials,data) {
    var stack1, helper, alias1=depth0 != null ? depth0 : (container.nullContext || {}), alias2=helpers.helperMissing, alias3="function", alias4=container.escapeExpression;

  return "<li class=\"comment"
    + ((stack1 = helpers["if"].call(alias1,(depth0 != null ? depth0.isNotSystemMessage : depth0),{"name":"if","hash":{},"fn":container.program(1, data, 0),"inverse":container.program(3, data, 0),"data":data})) != null ? stack1 : "")
    + "\" data-id=\""
    + alias4(((helper = (helper = helpers.id || (depth0 != null ? depth0.id : depth0)) != null ? helper : alias2),(typeof helper === alias3 ? helper.call(alias1,{"name":"id","hash":{},"data":data}) : helper)))
    + "\">\n	<div class=\"authorRow"
    + ((stack1 = helpers["if"].call(alias1,(depth0 != null ? depth0.isUserAuthor : depth0),{"name":"if","hash":{},"fn":container.program(5, data, 0),"inverse":container.noop,"data":data})) != null ? stack1 : "")
    + ((stack1 = helpers["if"].call(alias1,(depth0 != null ? depth0.isGuest : depth0),{"name":"if","hash":{},"fn":container.program(7, data, 0),"inverse":container.noop,"data":data})) != null ? stack1 : "")
    + "\">\n"
    + ((stack1 = helpers["if"].call(alias1,(depth0 != null ? depth0.isNotSystemMessage : depth0),{"name":"if","hash":{},"fn":container.program(9, data, 0),"inverse":container.noop,"data":data})) != null ? stack1 : "")
    + "		<div class=\"date has-tooltip"
    + ((stack1 = helpers["if"].call(alias1,(depth0 != null ? depth0.relativeDate : depth0),{"name":"if","hash":{},"fn":container.program(12, data, 0),"inverse":container.noop,"data":data})) != null ? stack1 : "")
    + "\" data-timestamp=\""
    + alias4(((helper = (helper = helpers.timestamp || (depth0 != null ? depth0.timestamp : depth0)) != null ? helper : alias2),(typeof helper === alias3 ? helper.call(alias1,{"name":"timestamp","hash":{},"data":data}) : helper)))
    + "\" title=\""
    + alias4(((helper = (helper = helpers.altDate || (depth0 != null ? depth0.altDate : depth0)) != null ? helper : alias2),(typeof helper === alias3 ? helper.call(alias1,{"name":"altDate","hash":{},"data":data}) : helper)))
    + "\">"
    + alias4(((helper = (helper = helpers.date || (depth0 != null ? depth0.date : depth0)) != null ? helper : alias2),(typeof helper === alias3 ? helper.call(alias1,{"name":"date","hash":{},"data":data}) : helper)))
    + "</div>\n	</div>\n	<div class=\"message\">"
    + ((stack1 = ((helper = (helper = helpers.formattedMessage || (depth0 != null ? depth0.formattedMessage : depth0)) != null ? helper : alias2),(typeof helper === alias3 ? helper.call(alias1,{"name":"formattedMessage","hash":{},"data":data}) : helper))) != null ? stack1 : "")
    + "</div>\n</li>\n";
},"useData":true});
templates['editabletextlabel'] = template({"1":function(container,depth0,helpers,partials,data) {
    var stack1;

  return "	<div class=\"edit-button\"><span class=\"icon button icon-rename\" "
    + ((stack1 = helpers["if"].call(depth0 != null ? depth0 : (container.nullContext || {}),(depth0 != null ? depth0.buttonTitle : depth0),{"name":"if","hash":{},"fn":container.program(2, data, 0),"inverse":container.noop,"data":data})) != null ? stack1 : "")
    + "></span></div>\n";
},"2":function(container,depth0,helpers,partials,data) {
    var helper;

  return " title=\""
    + container.escapeExpression(((helper = (helper = helpers.buttonTitle || (depth0 != null ? depth0.buttonTitle : depth0)) != null ? helper : helpers.helperMissing),(typeof helper === "function" ? helper.call(depth0 != null ? depth0 : (container.nullContext || {}),{"name":"buttonTitle","hash":{},"data":data}) : helper)))
    + "\" ";
},"4":function(container,depth0,helpers,partials,data) {
    var stack1, helper, alias1=depth0 != null ? depth0 : (container.nullContext || {});

  return "<div class=\"input-wrapper hidden-important\">\n	<input class=\"username\" "
    + ((stack1 = helpers["if"].call(alias1,(depth0 != null ? depth0.inputMaxLength : depth0),{"name":"if","hash":{},"fn":container.program(5, data, 0),"inverse":container.noop,"data":data})) != null ? stack1 : "")
    + " type=\"text\" value=\""
    + container.escapeExpression(((helper = (helper = helpers.inputValue || (depth0 != null ? depth0.inputValue : depth0)) != null ? helper : helpers.helperMissing),(typeof helper === "function" ? helper.call(alias1,{"name":"inputValue","hash":{},"data":data}) : helper)))
    + "\" "
    + ((stack1 = helpers["if"].call(alias1,(depth0 != null ? depth0.inputPlaceholder : depth0),{"name":"if","hash":{},"fn":container.program(7, data, 0),"inverse":container.noop,"data":data})) != null ? stack1 : "")
    + ">\n	<input type=\"submit\" value=\"\" class=\"icon icon-confirm confirm-button\"></div>\n</div>\n";
},"5":function(container,depth0,helpers,partials,data) {
    var helper;

  return " maxlength=\""
    + container.escapeExpression(((helper = (helper = helpers.inputMaxLength || (depth0 != null ? depth0.inputMaxLength : depth0)) != null ? helper : helpers.helperMissing),(typeof helper === "function" ? helper.call(depth0 != null ? depth0 : (container.nullContext || {}),{"name":"inputMaxLength","hash":{},"data":data}) : helper)))
    + "\" ";
},"7":function(container,depth0,helpers,partials,data) {
    var helper;

  return " placeholder=\""
    + container.escapeExpression(((helper = (helper = helpers.inputPlaceholder || (depth0 != null ? depth0.inputPlaceholder : depth0)) != null ? helper : helpers.helperMissing),(typeof helper === "function" ? helper.call(depth0 != null ? depth0 : (container.nullContext || {}),{"name":"inputPlaceholder","hash":{},"data":data}) : helper)))
    + "\" ";
},"compiler":[7,">= 4.0.0"],"main":function(container,depth0,helpers,partials,data) {
    var stack1, helper, alias1=depth0 != null ? depth0 : (container.nullContext || {}), alias2=helpers.helperMissing, alias3="function", alias4=container.escapeExpression;

  return "<div class=\"label-wrapper\">\n	<"
    + alias4(((helper = (helper = helpers.labelTagName || (depth0 != null ? depth0.labelTagName : depth0)) != null ? helper : alias2),(typeof helper === alias3 ? helper.call(alias1,{"name":"labelTagName","hash":{},"data":data}) : helper)))
    + " class=\"label\">"
    + alias4(((helper = (helper = helpers.text || (depth0 != null ? depth0.text : depth0)) != null ? helper : alias2),(typeof helper === alias3 ? helper.call(alias1,{"name":"text","hash":{},"data":data}) : helper)))
    + "</"
    + alias4(((helper = (helper = helpers.labelTagName || (depth0 != null ? depth0.labelTagName : depth0)) != null ? helper : alias2),(typeof helper === alias3 ? helper.call(alias1,{"name":"labelTagName","hash":{},"data":data}) : helper)))
    + ">\n"
    + ((stack1 = helpers["if"].call(alias1,(depth0 != null ? depth0.editionEnabled : depth0),{"name":"if","hash":{},"fn":container.program(1, data, 0),"inverse":container.noop,"data":data})) != null ? stack1 : "")
    + "</div>\n"
    + ((stack1 = helpers["if"].call(alias1,(depth0 != null ? depth0.editionEnabled : depth0),{"name":"if","hash":{},"fn":container.program(4, data, 0),"inverse":container.noop,"data":data})) != null ? stack1 : "");
},"useData":true});
templates['localvideoview'] = template({"compiler":[7,">= 4.0.0"],"main":function(container,depth0,helpers,partials,data) {
    return "<video id=\"localVideo\"></video>\n<div class=\"avatar-container hidden\">\n	<div class=\"avatar\"></div>\n</div>\n<div class=\"nameIndicator\"></div>\n";
},"useData":true});
templates['mediacontrolsview'] = template({"compiler":[7,">= 4.0.0"],"main":function(container,depth0,helpers,partials,data) {
    var helper, alias1=depth0 != null ? depth0 : (container.nullContext || {}), alias2=helpers.helperMissing, alias3="function", alias4=container.escapeExpression;

  return "<button id=\"mute\" class=\"icon-audio force-icon-white-in-call icon-shadow\" data-placement=\"top\" data-toggle=\"tooltip\" data-original-title=\""
    + alias4(((helper = (helper = helpers.muteAudioButtonTitle || (depth0 != null ? depth0.muteAudioButtonTitle : depth0)) != null ? helper : alias2),(typeof helper === alias3 ? helper.call(alias1,{"name":"muteAudioButtonTitle","hash":{},"data":data}) : helper)))
    + "\"></button>\n<button id=\"hideVideo\" class=\"icon-video force-icon-white-in-call icon-shadow\" data-placement=\"top\" data-toggle=\"tooltip\" data-original-title=\""
    + alias4(((helper = (helper = helpers.hideVideoButtonTitle || (depth0 != null ? depth0.hideVideoButtonTitle : depth0)) != null ? helper : alias2),(typeof helper === alias3 ? helper.call(alias1,{"name":"hideVideoButtonTitle","hash":{},"data":data}) : helper)))
    + "\"></button>\n<button id=\"screensharing-button\" class=\"app-navigation-entry-utils-menu-button icon-screen-off force-icon-white-in-call icon-shadow screensharing-disabled\" data-placement=\"top\" data-toggle=\"tooltip\" data-original-title=\""
    + alias4(((helper = (helper = helpers.screensharingButtonTitle || (depth0 != null ? depth0.screensharingButtonTitle : depth0)) != null ? helper : alias2),(typeof helper === alias3 ? helper.call(alias1,{"name":"screensharingButtonTitle","hash":{},"data":data}) : helper)))
    + "\"></button>\n<div id=\"screensharing-menu\" class=\"app-navigation-entry-menu\">\n	<ul>\n		<li id=\"share-screen-entry\">\n			<button id=\"share-screen-button\">\n				<span class=\"icon-screen\"></span>\n				<span>"
    + alias4(((helper = (helper = helpers.shareScreenButtonTitle || (depth0 != null ? depth0.shareScreenButtonTitle : depth0)) != null ? helper : alias2),(typeof helper === alias3 ? helper.call(alias1,{"name":"shareScreenButtonTitle","hash":{},"data":data}) : helper)))
    + "</span>\n			</button>\n		</li>\n		<li id=\"share-window-entry\">\n			<button id=\"share-window-button\">\n				<span class=\"icon-share-window\"></span>\n				<span>"
    + alias4(((helper = (helper = helpers.shareWindowButtonTitle || (depth0 != null ? depth0.shareWindowButtonTitle : depth0)) != null ? helper : alias2),(typeof helper === alias3 ? helper.call(alias1,{"name":"shareWindowButtonTitle","hash":{},"data":data}) : helper)))
    + "</span>\n			</button>\n		</li>\n		<li id=\"show-screen-entry\">\n			<button id=\"show-screen-button\">\n				<span class=\"icon-screen\"></span>\n				<span>"
    + alias4(((helper = (helper = helpers.showScreenButtonTitle || (depth0 != null ? depth0.showScreenButtonTitle : depth0)) != null ? helper : alias2),(typeof helper === alias3 ? helper.call(alias1,{"name":"showScreenButtonTitle","hash":{},"data":data}) : helper)))
    + "</span>\n			</button>\n		</li>\n		<li id=\"stop-screen-entry\">\n			<button id=\"stop-screen-button\">\n				<span class=\"icon-screen-off\"></span>\n				<span>"
    + alias4(((helper = (helper = helpers.stopScreenButtonTitle || (depth0 != null ? depth0.stopScreenButtonTitle : depth0)) != null ? helper : alias2),(typeof helper === alias3 ? helper.call(alias1,{"name":"stopScreenButtonTitle","hash":{},"data":data}) : helper)))
    + "</span>\n			</button>\n		</li>\n	</ul>\n</div>\n";
},"useData":true});
templates['participantlistview'] = template({"1":function(container,depth0,helpers,partials,data) {
    var helper;

  return "<span class=\"participant-moderator-indicator\">"
    + container.escapeExpression(((helper = (helper = helpers.moderatorIndicator || (depth0 != null ? depth0.moderatorIndicator : depth0)) != null ? helper : helpers.helperMissing),(typeof helper === "function" ? helper.call(depth0 != null ? depth0 : (container.nullContext || {}),{"name":"moderatorIndicator","hash":{},"data":data}) : helper)))
    + "</span>";
},"3":function(container,depth0,helpers,partials,data) {
    return "<span class=\"icon icon-video\"></span>";
},"5":function(container,depth0,helpers,partials,data) {
    var stack1, helper, alias1=depth0 != null ? depth0 : (container.nullContext || {});

  return "<div class=\"participant-entry-utils\">\n	<ul>\n		<li class=\"participant-entry-utils-menu-button\">\n			<button class=\"icon icon-more\"></button>\n			<span class=\"icon icon-loading-small hidden\"></span>\n		</li>\n	</ul>\n</div>\n<div class=\"popovermenu bubble menu\">\n	<ul class=\"popovermenu-list\">\n"
    + ((stack1 = helpers["if"].call(alias1,(depth0 != null ? depth0.canBeDemoted : depth0),{"name":"if","hash":{},"fn":container.program(6, data, 0),"inverse":container.program(8, data, 0),"data":data})) != null ? stack1 : "")
    + "		<li>\n			<button class=\"remove-participant\">\n				<span class=\"icon icon-delete\"></span>\n				<span>"
    + container.escapeExpression(((helper = (helper = helpers.removeParticipantText || (depth0 != null ? depth0.removeParticipantText : depth0)) != null ? helper : helpers.helperMissing),(typeof helper === "function" ? helper.call(alias1,{"name":"removeParticipantText","hash":{},"data":data}) : helper)))
    + "</span>\n			</button>\n		</li>\n	</ul>\n</div>\n";
},"6":function(container,depth0,helpers,partials,data) {
    var helper;

  return "		<li>\n			<button class=\"demote-moderator\">\n				<span class=\"icon icon-star\"></span>\n				<span>"
    + container.escapeExpression(((helper = (helper = helpers.demoteModeratorText || (depth0 != null ? depth0.demoteModeratorText : depth0)) != null ? helper : helpers.helperMissing),(typeof helper === "function" ? helper.call(depth0 != null ? depth0 : (container.nullContext || {}),{"name":"demoteModeratorText","hash":{},"data":data}) : helper)))
    + "</span>\n			</button>\n		</li>\n";
},"8":function(container,depth0,helpers,partials,data) {
    var stack1;

  return ((stack1 = helpers["if"].call(depth0 != null ? depth0 : (container.nullContext || {}),(depth0 != null ? depth0.canBePromoted : depth0),{"name":"if","hash":{},"fn":container.program(9, data, 0),"inverse":container.noop,"data":data})) != null ? stack1 : "");
},"9":function(container,depth0,helpers,partials,data) {
    var helper;

  return "		<li>\n			<button class=\"promote-moderator\">\n				<span class=\"icon icon-rename\"></span>\n				<span>"
    + container.escapeExpression(((helper = (helper = helpers.promoteModeratorText || (depth0 != null ? depth0.promoteModeratorText : depth0)) != null ? helper : helpers.helperMissing),(typeof helper === "function" ? helper.call(depth0 != null ? depth0 : (container.nullContext || {}),{"name":"promoteModeratorText","hash":{},"data":data}) : helper)))
    + "</span>\n			</button>\n		</li>\n";
},"compiler":[7,">= 4.0.0"],"main":function(container,depth0,helpers,partials,data) {
    var stack1, helper, alias1=depth0 != null ? depth0 : (container.nullContext || {}), alias2=helpers.helperMissing, alias3="function", alias4=container.escapeExpression;

  return "<a class=\"participant-entry-link\" href=\"#\" data-sessionId=\""
    + alias4(((helper = (helper = helpers.sessionId || (depth0 != null ? depth0.sessionId : depth0)) != null ? helper : alias2),(typeof helper === alias3 ? helper.call(alias1,{"name":"sessionId","hash":{},"data":data}) : helper)))
    + "\">\n	<div class=\"avatar\"></div>\n	"
    + alias4(((helper = (helper = helpers.name || (depth0 != null ? depth0.name : depth0)) != null ? helper : alias2),(typeof helper === alias3 ? helper.call(alias1,{"name":"name","hash":{},"data":data}) : helper)))
    + "\n	"
    + ((stack1 = helpers["if"].call(alias1,(depth0 != null ? depth0.participantIsOwner : depth0),{"name":"if","hash":{},"fn":container.program(1, data, 0),"inverse":container.noop,"data":data})) != null ? stack1 : "")
    + "\n	"
    + ((stack1 = helpers["if"].call(alias1,(depth0 != null ? depth0.participantIsModerator : depth0),{"name":"if","hash":{},"fn":container.program(1, data, 0),"inverse":container.noop,"data":data})) != null ? stack1 : "")
    + "\n	"
    + ((stack1 = helpers["if"].call(alias1,(depth0 != null ? depth0.participantIsGuestModerator : depth0),{"name":"if","hash":{},"fn":container.program(1, data, 0),"inverse":container.noop,"data":data})) != null ? stack1 : "")
    + "\n	"
    + ((stack1 = helpers["if"].call(alias1,(depth0 != null ? depth0.inCall : depth0),{"name":"if","hash":{},"fn":container.program(3, data, 0),"inverse":container.noop,"data":data})) != null ? stack1 : "")
    + "\n</a>\n"
    + ((stack1 = helpers["if"].call(alias1,(depth0 != null ? depth0.canModerate : depth0),{"name":"if","hash":{},"fn":container.program(5, data, 0),"inverse":container.noop,"data":data})) != null ? stack1 : "");
},"useData":true});
templates['richobjectstringparser_call'] = template({"compiler":[7,">= 4.0.0"],"main":function(container,depth0,helpers,partials,data) {
    var helper;

  return "<span class=\"atwho-inserted\" contenteditable=\"false\"><span class=\"mention-call avatar-name-wrapper currentUser\"><span class=\"avatar icon icon-contacts\" data-user-id=\"all\"></span><strong>"
    + container.escapeExpression(((helper = (helper = helpers.name || (depth0 != null ? depth0.name : depth0)) != null ? helper : helpers.helperMissing),(typeof helper === "function" ? helper.call(depth0 != null ? depth0 : (container.nullContext || {}),{"name":"name","hash":{},"data":data}) : helper)))
    + "</strong></span></span>\n";
},"useData":true});
templates['richobjectstringparser_filepreview'] = template({"compiler":[7,">= 4.0.0"],"main":function(container,depth0,helpers,partials,data) {
    var helper, alias1=depth0 != null ? depth0 : (container.nullContext || {}), alias2=helpers.helperMissing, alias3="function", alias4=container.escapeExpression;

  return "<a href=\""
    + alias4(((helper = (helper = helpers.link || (depth0 != null ? depth0.link : depth0)) != null ? helper : alias2),(typeof helper === alias3 ? helper.call(alias1,{"name":"link","hash":{},"data":data}) : helper)))
    + "\" class=\"filePreviewContainer\" target=\"_blank\" rel=\"noopener noreferrer\">\n	<span class=\"filePreview\" data-file-id=\""
    + alias4(((helper = (helper = helpers.id || (depth0 != null ? depth0.id : depth0)) != null ? helper : alias2),(typeof helper === alias3 ? helper.call(alias1,{"name":"id","hash":{},"data":data}) : helper)))
    + "\" data-mimetype=\""
    + alias4(((helper = (helper = helpers.mimetype || (depth0 != null ? depth0.mimetype : depth0)) != null ? helper : alias2),(typeof helper === alias3 ? helper.call(alias1,{"name":"mimetype","hash":{},"data":data}) : helper)))
    + "\" data-preview-available=\""
    + alias4(((helper = (helper = helpers["preview-available"] || (depth0 != null ? depth0["preview-available"] : depth0)) != null ? helper : alias2),(typeof helper === alias3 ? helper.call(alias1,{"name":"preview-available","hash":{},"data":data}) : helper)))
    + "\"></span>\n	<strong>"
    + alias4(((helper = (helper = helpers.name || (depth0 != null ? depth0.name : depth0)) != null ? helper : alias2),(typeof helper === alias3 ? helper.call(alias1,{"name":"name","hash":{},"data":data}) : helper)))
    + "</strong>\n</a>\n";
},"useData":true});
templates['richobjectstringparser_unknown'] = template({"compiler":[7,">= 4.0.0"],"main":function(container,depth0,helpers,partials,data) {
    var helper;

  return "<strong>"
    + container.escapeExpression(((helper = (helper = helpers.name || (depth0 != null ? depth0.name : depth0)) != null ? helper : helpers.helperMissing),(typeof helper === "function" ? helper.call(depth0 != null ? depth0 : (container.nullContext || {}),{"name":"name","hash":{},"data":data}) : helper)))
    + "</strong>\n";
},"useData":true});
templates['richobjectstringparser_unknownlink'] = template({"compiler":[7,">= 4.0.0"],"main":function(container,depth0,helpers,partials,data) {
    var helper, alias1=depth0 != null ? depth0 : (container.nullContext || {}), alias2=helpers.helperMissing, alias3="function", alias4=container.escapeExpression;

  return "<a href=\""
    + alias4(((helper = (helper = helpers.link || (depth0 != null ? depth0.link : depth0)) != null ? helper : alias2),(typeof helper === alias3 ? helper.call(alias1,{"name":"link","hash":{},"data":data}) : helper)))
    + "\" class=\"external\" target=\"_blank\" rel=\"noopener noreferrer\"><strong>"
    + alias4(((helper = (helper = helpers.name || (depth0 != null ? depth0.name : depth0)) != null ? helper : alias2),(typeof helper === alias3 ? helper.call(alias1,{"name":"name","hash":{},"data":data}) : helper)))
    + "</strong></a>\n";
},"useData":true});
templates['richobjectstringparser_userlocal'] = template({"1":function(container,depth0,helpers,partials,data) {
    return "currentUser";
},"compiler":[7,">= 4.0.0"],"main":function(container,depth0,helpers,partials,data) {
    var stack1, helper, alias1=depth0 != null ? depth0 : (container.nullContext || {}), alias2=helpers.helperMissing, alias3="function", alias4=container.escapeExpression;

  return "<span class=\"atwho-inserted\" contenteditable=\"false\"><span class=\"mention-user avatar-name-wrapper "
    + ((stack1 = helpers["if"].call(alias1,(depth0 != null ? depth0.isCurrentUser : depth0),{"name":"if","hash":{},"fn":container.program(1, data, 0),"inverse":container.noop,"data":data})) != null ? stack1 : "")
    + "\"><span class=\"avatar\" data-user-id=\""
    + alias4(((helper = (helper = helpers.id || (depth0 != null ? depth0.id : depth0)) != null ? helper : alias2),(typeof helper === alias3 ? helper.call(alias1,{"name":"id","hash":{},"data":data}) : helper)))
    + "\" data-user-display-name=\""
    + alias4(((helper = (helper = helpers.name || (depth0 != null ? depth0.name : depth0)) != null ? helper : alias2),(typeof helper === alias3 ? helper.call(alias1,{"name":"name","hash":{},"data":data}) : helper)))
    + "\"></span><strong>"
    + alias4(((helper = (helper = helpers.name || (depth0 != null ? depth0.name : depth0)) != null ? helper : alias2),(typeof helper === alias3 ? helper.call(alias1,{"name":"name","hash":{},"data":data}) : helper)))
    + "</strong></span></span>\n";
},"useData":true});
templates['screenview'] = template({"compiler":[7,">= 4.0.0"],"main":function(container,depth0,helpers,partials,data) {
    return "<div class=\"nameIndicator\"></div>\n";
},"useData":true});
templates['videoview'] = template({"compiler":[7,">= 4.0.0"],"main":function(container,depth0,helpers,partials,data) {
    return "<div class=\"avatar-container\">\n	<div class=\"avatar\"></div>\n</div>\n<div class=\"nameIndicator\"></div>\n<div class=\"mediaIndicator\">\n	<button class=\"muteIndicator force-icon-white-in-call icon-shadow icon-audio-off audio-on\" disabled=\"true\"/>\n	<button class=\"hideRemoteVideo force-icon-white-in-call icon-shadow icon-video\"/>\n	<button class=\"screensharingIndicator force-icon-white-in-call icon-shadow icon-screen screen-off\"/>\n	<button class=\"iceFailedIndicator force-icon-white-in-call icon-shadow icon-error not-failed\" disabled=\"true\"/>\n</div>\n";
},"useData":true});
})();