/* Copyright 2017 Mozilla Foundation
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */
'use strict';

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.AnnotationFactory = exports.AnnotationBorderStyle = exports.Annotation = undefined;

var _util = require('../shared/util');

var _obj = require('./obj');

var _primitives = require('./primitives');

var _colorspace = require('./colorspace');

var _evaluator = require('./evaluator');

var _stream = require('./stream');

function AnnotationFactory() {}
AnnotationFactory.prototype = {
  create: function AnnotationFactory_create(xref, ref, pdfManager, idFactory) {
    var dict = xref.fetchIfRef(ref);
    if (!(0, _primitives.isDict)(dict)) {
      return;
    }
    var id = (0, _primitives.isRef)(ref) ? ref.toString() : 'annot_' + idFactory.createObjId();
    var subtype = dict.get('Subtype');
    subtype = (0, _primitives.isName)(subtype) ? subtype.name : null;
    var parameters = {
      xref: xref,
      dict: dict,
      ref: (0, _primitives.isRef)(ref) ? ref : null,
      subtype: subtype,
      id: id,
      pdfManager: pdfManager
    };
    switch (subtype) {
      case 'Link':
        return new LinkAnnotation(parameters);
      case 'Text':
        return new TextAnnotation(parameters);
      case 'Widget':
        var fieldType = _util.Util.getInheritableProperty(dict, 'FT');
        fieldType = (0, _primitives.isName)(fieldType) ? fieldType.name : null;
        switch (fieldType) {
          case 'Tx':
            return new TextWidgetAnnotation(parameters);
          case 'Btn':
            return new ButtonWidgetAnnotation(parameters);
          case 'Ch':
            return new ChoiceWidgetAnnotation(parameters);
        }
        (0, _util.warn)('Unimplemented widget field type "' + fieldType + '", ' + 'falling back to base field type.');
        return new WidgetAnnotation(parameters);
      case 'Popup':
        return new PopupAnnotation(parameters);
      case 'Line':
        return new LineAnnotation(parameters);
      case 'Highlight':
        return new HighlightAnnotation(parameters);
      case 'Underline':
        return new UnderlineAnnotation(parameters);
      case 'Squiggly':
        return new SquigglyAnnotation(parameters);
      case 'StrikeOut':
        return new StrikeOutAnnotation(parameters);
      case 'FileAttachment':
        return new FileAttachmentAnnotation(parameters);
      default:
        if (!subtype) {
          (0, _util.warn)('Annotation is missing the required /Subtype.');
        } else {
          (0, _util.warn)('Unimplemented annotation type "' + subtype + '", ' + 'falling back to base annotation.');
        }
        return new Annotation(parameters);
    }
  }
};
var Annotation = function AnnotationClosure() {
  function getTransformMatrix(rect, bbox, matrix) {
    var bounds = _util.Util.getAxialAlignedBoundingBox(bbox, matrix);
    var minX = bounds[0];
    var minY = bounds[1];
    var maxX = bounds[2];
    var maxY = bounds[3];
    if (minX === maxX || minY === maxY) {
      return [1, 0, 0, 1, rect[0], rect[1]];
    }
    var xRatio = (rect[2] - rect[0]) / (maxX - minX);
    var yRatio = (rect[3] - rect[1]) / (maxY - minY);
    return [xRatio, 0, 0, yRatio, rect[0] - minX * xRatio, rect[1] - minY * yRatio];
  }
  function Annotation(params) {
    var dict = params.dict;
    this.setFlags(dict.get('F'));
    this.setRectangle(dict.getArray('Rect'));
    this.setColor(dict.getArray('C'));
    this.setBorderStyle(dict);
    this.setAppearance(dict);
    this.data = {};
    this.data.id = params.id;
    this.data.subtype = params.subtype;
    this.data.annotationFlags = this.flags;
    this.data.rect = this.rectangle;
    this.data.color = this.color;
    this.data.borderStyle = this.borderStyle;
    this.data.hasAppearance = !!this.appearance;
  }
  Annotation.prototype = {
    _hasFlag: function Annotation_hasFlag(flags, flag) {
      return !!(flags & flag);
    },
    _isViewable: function Annotation_isViewable(flags) {
      return !this._hasFlag(flags, _util.AnnotationFlag.INVISIBLE) && !this._hasFlag(flags, _util.AnnotationFlag.HIDDEN) && !this._hasFlag(flags, _util.AnnotationFlag.NOVIEW);
    },
    _isPrintable: function AnnotationFlag_isPrintable(flags) {
      return this._hasFlag(flags, _util.AnnotationFlag.PRINT) && !this._hasFlag(flags, _util.AnnotationFlag.INVISIBLE) && !this._hasFlag(flags, _util.AnnotationFlag.HIDDEN);
    },
    get viewable() {
      if (this.flags === 0) {
        return true;
      }
      return this._isViewable(this.flags);
    },
    get printable() {
      if (this.flags === 0) {
        return false;
      }
      return this._isPrintable(this.flags);
    },
    setFlags: function Annotation_setFlags(flags) {
      this.flags = (0, _util.isInt)(flags) && flags > 0 ? flags : 0;
    },
    hasFlag: function Annotation_hasFlag(flag) {
      return this._hasFlag(this.flags, flag);
    },
    setRectangle: function Annotation_setRectangle(rectangle) {
      if ((0, _util.isArray)(rectangle) && rectangle.length === 4) {
        this.rectangle = _util.Util.normalizeRect(rectangle);
      } else {
        this.rectangle = [0, 0, 0, 0];
      }
    },
    setColor: function Annotation_setColor(color) {
      var rgbColor = new Uint8Array(3);
      if (!(0, _util.isArray)(color)) {
        this.color = rgbColor;
        return;
      }
      switch (color.length) {
        case 0:
          this.color = null;
          break;
        case 1:
          _colorspace.ColorSpace.singletons.gray.getRgbItem(color, 0, rgbColor, 0);
          this.color = rgbColor;
          break;
        case 3:
          _colorspace.ColorSpace.singletons.rgb.getRgbItem(color, 0, rgbColor, 0);
          this.color = rgbColor;
          break;
        case 4:
          _colorspace.ColorSpace.singletons.cmyk.getRgbItem(color, 0, rgbColor, 0);
          this.color = rgbColor;
          break;
        default:
          this.color = rgbColor;
          break;
      }
    },
    setBorderStyle: function Annotation_setBorderStyle(borderStyle) {
      this.borderStyle = new AnnotationBorderStyle();
      if (!(0, _primitives.isDict)(borderStyle)) {
        return;
      }
      if (borderStyle.has('BS')) {
        var dict = borderStyle.get('BS');
        var dictType = dict.get('Type');
        if (!dictType || (0, _primitives.isName)(dictType, 'Border')) {
          this.borderStyle.setWidth(dict.get('W'));
          this.borderStyle.setStyle(dict.get('S'));
          this.borderStyle.setDashArray(dict.getArray('D'));
        }
      } else if (borderStyle.has('Border')) {
        var array = borderStyle.getArray('Border');
        if ((0, _util.isArray)(array) && array.length >= 3) {
          this.borderStyle.setHorizontalCornerRadius(array[0]);
          this.borderStyle.setVerticalCornerRadius(array[1]);
          this.borderStyle.setWidth(array[2]);
          if (array.length === 4) {
            this.borderStyle.setDashArray(array[3]);
          }
        }
      } else {
        this.borderStyle.setWidth(0);
      }
    },
    setAppearance: function Annotation_setAppearance(dict) {
      this.appearance = null;
      var appearanceStates = dict.get('AP');
      if (!(0, _primitives.isDict)(appearanceStates)) {
        return;
      }
      var normalAppearanceState = appearanceStates.get('N');
      if ((0, _primitives.isStream)(normalAppearanceState)) {
        this.appearance = normalAppearanceState;
        return;
      }
      if (!(0, _primitives.isDict)(normalAppearanceState)) {
        return;
      }
      var as = dict.get('AS');
      if (!(0, _primitives.isName)(as) || !normalAppearanceState.has(as.name)) {
        return;
      }
      this.appearance = normalAppearanceState.get(as.name);
    },
    _preparePopup: function Annotation_preparePopup(dict) {
      if (!dict.has('C')) {
        this.data.color = null;
      }
      this.data.hasPopup = dict.has('Popup');
      this.data.title = (0, _util.stringToPDFString)(dict.get('T') || '');
      this.data.contents = (0, _util.stringToPDFString)(dict.get('Contents') || '');
    },
    loadResources: function Annotation_loadResources(keys) {
      return this.appearance.dict.getAsync('Resources').then(function (resources) {
        if (!resources) {
          return;
        }
        var objectLoader = new _obj.ObjectLoader(resources, keys, resources.xref);
        return objectLoader.load().then(function () {
          return resources;
        });
      });
    },
    getOperatorList: function Annotation_getOperatorList(evaluator, task, renderForms) {
      var _this = this;

      if (!this.appearance) {
        return Promise.resolve(new _evaluator.OperatorList());
      }
      var data = this.data;
      var appearanceDict = this.appearance.dict;
      var resourcesPromise = this.loadResources(['ExtGState', 'ColorSpace', 'Pattern', 'Shading', 'XObject', 'Font']);
      var bbox = appearanceDict.getArray('BBox') || [0, 0, 1, 1];
      var matrix = appearanceDict.getArray('Matrix') || [1, 0, 0, 1, 0, 0];
      var transform = getTransformMatrix(data.rect, bbox, matrix);
      return resourcesPromise.then(function (resources) {
        var opList = new _evaluator.OperatorList();
        opList.addOp(_util.OPS.beginAnnotation, [data.rect, transform, matrix]);
        return evaluator.getOperatorList({
          stream: _this.appearance,
          task: task,
          resources: resources,
          operatorList: opList
        }).then(function () {
          opList.addOp(_util.OPS.endAnnotation, []);
          _this.appearance.reset();
          return opList;
        });
      });
    }
  };
  return Annotation;
}();
var AnnotationBorderStyle = function AnnotationBorderStyleClosure() {
  function AnnotationBorderStyle() {
    this.width = 1;
    this.style = _util.AnnotationBorderStyleType.SOLID;
    this.dashArray = [3];
    this.horizontalCornerRadius = 0;
    this.verticalCornerRadius = 0;
  }
  AnnotationBorderStyle.prototype = {
    setWidth: function AnnotationBorderStyle_setWidth(width) {
      if (width === (width | 0)) {
        this.width = width;
      }
    },
    setStyle: function AnnotationBorderStyle_setStyle(style) {
      if (!style) {
        return;
      }
      switch (style.name) {
        case 'S':
          this.style = _util.AnnotationBorderStyleType.SOLID;
          break;
        case 'D':
          this.style = _util.AnnotationBorderStyleType.DASHED;
          break;
        case 'B':
          this.style = _util.AnnotationBorderStyleType.BEVELED;
          break;
        case 'I':
          this.style = _util.AnnotationBorderStyleType.INSET;
          break;
        case 'U':
          this.style = _util.AnnotationBorderStyleType.UNDERLINE;
          break;
        default:
          break;
      }
    },
    setDashArray: function AnnotationBorderStyle_setDashArray(dashArray) {
      if ((0, _util.isArray)(dashArray) && dashArray.length > 0) {
        var isValid = true;
        var allZeros = true;
        for (var i = 0, len = dashArray.length; i < len; i++) {
          var element = dashArray[i];
          var validNumber = +element >= 0;
          if (!validNumber) {
            isValid = false;
            break;
          } else if (element > 0) {
            allZeros = false;
          }
        }
        if (isValid && !allZeros) {
          this.dashArray = dashArray;
        } else {
          this.width = 0;
        }
      } else if (dashArray) {
        this.width = 0;
      }
    },
    setHorizontalCornerRadius: function AnnotationBorderStyle_setHorizontalCornerRadius(radius) {
      if (radius === (radius | 0)) {
        this.horizontalCornerRadius = radius;
      }
    },
    setVerticalCornerRadius: function AnnotationBorderStyle_setVerticalCornerRadius(radius) {
      if (radius === (radius | 0)) {
        this.verticalCornerRadius = radius;
      }
    }
  };
  return AnnotationBorderStyle;
}();
var WidgetAnnotation = function WidgetAnnotationClosure() {
  function WidgetAnnotation(params) {
    Annotation.call(this, params);
    var dict = params.dict;
    var data = this.data;
    data.annotationType = _util.AnnotationType.WIDGET;
    data.fieldName = this._constructFieldName(dict);
    data.fieldValue = _util.Util.getInheritableProperty(dict, 'V', true);
    data.alternativeText = (0, _util.stringToPDFString)(dict.get('TU') || '');
    data.defaultAppearance = _util.Util.getInheritableProperty(dict, 'DA') || '';
    var fieldType = _util.Util.getInheritableProperty(dict, 'FT');
    data.fieldType = (0, _primitives.isName)(fieldType) ? fieldType.name : null;
    this.fieldResources = _util.Util.getInheritableProperty(dict, 'DR') || _primitives.Dict.empty;
    data.fieldFlags = _util.Util.getInheritableProperty(dict, 'Ff');
    if (!(0, _util.isInt)(data.fieldFlags) || data.fieldFlags < 0) {
      data.fieldFlags = 0;
    }
    data.readOnly = this.hasFieldFlag(_util.AnnotationFieldFlag.READONLY);
    if (data.fieldType === 'Sig') {
      this.setFlags(_util.AnnotationFlag.HIDDEN);
    }
  }
  _util.Util.inherit(WidgetAnnotation, Annotation, {
    _constructFieldName: function WidgetAnnotation_constructFieldName(dict) {
      if (!dict.has('T') && !dict.has('Parent')) {
        (0, _util.warn)('Unknown field name, falling back to empty field name.');
        return '';
      }
      if (!dict.has('Parent')) {
        return (0, _util.stringToPDFString)(dict.get('T'));
      }
      var fieldName = [];
      if (dict.has('T')) {
        fieldName.unshift((0, _util.stringToPDFString)(dict.get('T')));
      }
      var loopDict = dict;
      while (loopDict.has('Parent')) {
        loopDict = loopDict.get('Parent');
        if (!(0, _primitives.isDict)(loopDict)) {
          break;
        }
        if (loopDict.has('T')) {
          fieldName.unshift((0, _util.stringToPDFString)(loopDict.get('T')));
        }
      }
      return fieldName.join('.');
    },
    hasFieldFlag: function WidgetAnnotation_hasFieldFlag(flag) {
      return !!(this.data.fieldFlags & flag);
    }
  });
  return WidgetAnnotation;
}();
var TextWidgetAnnotation = function TextWidgetAnnotationClosure() {
  function TextWidgetAnnotation(params) {
    WidgetAnnotation.call(this, params);
    this.data.fieldValue = (0, _util.stringToPDFString)(this.data.fieldValue || '');
    var alignment = _util.Util.getInheritableProperty(params.dict, 'Q');
    if (!(0, _util.isInt)(alignment) || alignment < 0 || alignment > 2) {
      alignment = null;
    }
    this.data.textAlignment = alignment;
    var maximumLength = _util.Util.getInheritableProperty(params.dict, 'MaxLen');
    if (!(0, _util.isInt)(maximumLength) || maximumLength < 0) {
      maximumLength = null;
    }
    this.data.maxLen = maximumLength;
    this.data.multiLine = this.hasFieldFlag(_util.AnnotationFieldFlag.MULTILINE);
    this.data.comb = this.hasFieldFlag(_util.AnnotationFieldFlag.COMB) && !this.hasFieldFlag(_util.AnnotationFieldFlag.MULTILINE) && !this.hasFieldFlag(_util.AnnotationFieldFlag.PASSWORD) && !this.hasFieldFlag(_util.AnnotationFieldFlag.FILESELECT) && this.data.maxLen !== null;
  }
  _util.Util.inherit(TextWidgetAnnotation, WidgetAnnotation, {
    getOperatorList: function TextWidgetAnnotation_getOperatorList(evaluator, task, renderForms) {
      var operatorList = new _evaluator.OperatorList();
      if (renderForms) {
        return Promise.resolve(operatorList);
      }
      if (this.appearance) {
        return Annotation.prototype.getOperatorList.call(this, evaluator, task, renderForms);
      }
      if (!this.data.defaultAppearance) {
        return Promise.resolve(operatorList);
      }
      var stream = new _stream.Stream((0, _util.stringToBytes)(this.data.defaultAppearance));
      return evaluator.getOperatorList({
        stream: stream,
        task: task,
        resources: this.fieldResources,
        operatorList: operatorList
      }).then(function () {
        return operatorList;
      });
    }
  });
  return TextWidgetAnnotation;
}();
var ButtonWidgetAnnotation = function ButtonWidgetAnnotationClosure() {
  function ButtonWidgetAnnotation(params) {
    WidgetAnnotation.call(this, params);
    this.data.checkBox = !this.hasFieldFlag(_util.AnnotationFieldFlag.RADIO) && !this.hasFieldFlag(_util.AnnotationFieldFlag.PUSHBUTTON);
    if (this.data.checkBox) {
      if (!(0, _primitives.isName)(this.data.fieldValue)) {
        return;
      }
      this.data.fieldValue = this.data.fieldValue.name;
    }
    this.data.radioButton = this.hasFieldFlag(_util.AnnotationFieldFlag.RADIO) && !this.hasFieldFlag(_util.AnnotationFieldFlag.PUSHBUTTON);
    if (this.data.radioButton) {
      this.data.fieldValue = this.data.buttonValue = null;
      var fieldParent = params.dict.get('Parent');
      if ((0, _primitives.isDict)(fieldParent) && fieldParent.has('V')) {
        var fieldParentValue = fieldParent.get('V');
        if ((0, _primitives.isName)(fieldParentValue)) {
          this.data.fieldValue = fieldParentValue.name;
        }
      }
      var appearanceStates = params.dict.get('AP');
      if (!(0, _primitives.isDict)(appearanceStates)) {
        return;
      }
      var normalAppearanceState = appearanceStates.get('N');
      if (!(0, _primitives.isDict)(normalAppearanceState)) {
        return;
      }
      var keys = normalAppearanceState.getKeys();
      for (var i = 0, ii = keys.length; i < ii; i++) {
        if (keys[i] !== 'Off') {
          this.data.buttonValue = keys[i];
          break;
        }
      }
    }
  }
  _util.Util.inherit(ButtonWidgetAnnotation, WidgetAnnotation, {
    getOperatorList: function ButtonWidgetAnnotation_getOperatorList(evaluator, task, renderForms) {
      var operatorList = new _evaluator.OperatorList();
      if (renderForms) {
        return Promise.resolve(operatorList);
      }
      if (this.appearance) {
        return Annotation.prototype.getOperatorList.call(this, evaluator, task, renderForms);
      }
      return Promise.resolve(operatorList);
    }
  });
  return ButtonWidgetAnnotation;
}();
var ChoiceWidgetAnnotation = function ChoiceWidgetAnnotationClosure() {
  function ChoiceWidgetAnnotation(params) {
    WidgetAnnotation.call(this, params);
    this.data.options = [];
    var options = _util.Util.getInheritableProperty(params.dict, 'Opt');
    if ((0, _util.isArray)(options)) {
      var xref = params.xref;
      for (var i = 0, ii = options.length; i < ii; i++) {
        var option = xref.fetchIfRef(options[i]);
        var isOptionArray = (0, _util.isArray)(option);
        this.data.options[i] = {
          exportValue: isOptionArray ? xref.fetchIfRef(option[0]) : option,
          displayValue: isOptionArray ? xref.fetchIfRef(option[1]) : option
        };
      }
    }
    if (!(0, _util.isArray)(this.data.fieldValue)) {
      this.data.fieldValue = [this.data.fieldValue];
    }
    this.data.combo = this.hasFieldFlag(_util.AnnotationFieldFlag.COMBO);
    this.data.multiSelect = this.hasFieldFlag(_util.AnnotationFieldFlag.MULTISELECT);
  }
  _util.Util.inherit(ChoiceWidgetAnnotation, WidgetAnnotation, {
    getOperatorList: function ChoiceWidgetAnnotation_getOperatorList(evaluator, task, renderForms) {
      var operatorList = new _evaluator.OperatorList();
      if (renderForms) {
        return Promise.resolve(operatorList);
      }
      return Annotation.prototype.getOperatorList.call(this, evaluator, task, renderForms);
    }
  });
  return ChoiceWidgetAnnotation;
}();
var TextAnnotation = function TextAnnotationClosure() {
  var DEFAULT_ICON_SIZE = 22;
  function TextAnnotation(parameters) {
    Annotation.call(this, parameters);
    this.data.annotationType = _util.AnnotationType.TEXT;
    if (this.data.hasAppearance) {
      this.data.name = 'NoIcon';
    } else {
      this.data.rect[1] = this.data.rect[3] - DEFAULT_ICON_SIZE;
      this.data.rect[2] = this.data.rect[0] + DEFAULT_ICON_SIZE;
      this.data.name = parameters.dict.has('Name') ? parameters.dict.get('Name').name : 'Note';
    }
    this._preparePopup(parameters.dict);
  }
  _util.Util.inherit(TextAnnotation, Annotation, {});
  return TextAnnotation;
}();
var LinkAnnotation = function LinkAnnotationClosure() {
  function LinkAnnotation(params) {
    Annotation.call(this, params);
    var data = this.data;
    data.annotationType = _util.AnnotationType.LINK;
    _obj.Catalog.parseDestDictionary({
      destDict: params.dict,
      resultObj: data,
      docBaseUrl: params.pdfManager.docBaseUrl
    });
  }
  _util.Util.inherit(LinkAnnotation, Annotation, {});
  return LinkAnnotation;
}();
var PopupAnnotation = function PopupAnnotationClosure() {
  function PopupAnnotation(parameters) {
    Annotation.call(this, parameters);
    this.data.annotationType = _util.AnnotationType.POPUP;
    var dict = parameters.dict;
    var parentItem = dict.get('Parent');
    if (!parentItem) {
      (0, _util.warn)('Popup annotation has a missing or invalid parent annotation.');
      return;
    }
    var parentSubtype = parentItem.get('Subtype');
    this.data.parentType = (0, _primitives.isName)(parentSubtype) ? parentSubtype.name : null;
    this.data.parentId = dict.getRaw('Parent').toString();
    this.data.title = (0, _util.stringToPDFString)(parentItem.get('T') || '');
    this.data.contents = (0, _util.stringToPDFString)(parentItem.get('Contents') || '');
    if (!parentItem.has('C')) {
      this.data.color = null;
    } else {
      this.setColor(parentItem.getArray('C'));
      this.data.color = this.color;
    }
    if (!this.viewable) {
      var parentFlags = parentItem.get('F');
      if (this._isViewable(parentFlags)) {
        this.setFlags(parentFlags);
      }
    }
  }
  _util.Util.inherit(PopupAnnotation, Annotation, {});
  return PopupAnnotation;
}();
var LineAnnotation = function LineAnnotationClosure() {
  function LineAnnotation(parameters) {
    Annotation.call(this, parameters);
    this.data.annotationType = _util.AnnotationType.LINE;
    var dict = parameters.dict;
    this.data.lineCoordinates = _util.Util.normalizeRect(dict.getArray('L'));
    this._preparePopup(dict);
  }
  _util.Util.inherit(LineAnnotation, Annotation, {});
  return LineAnnotation;
}();
var HighlightAnnotation = function HighlightAnnotationClosure() {
  function HighlightAnnotation(parameters) {
    Annotation.call(this, parameters);
    this.data.annotationType = _util.AnnotationType.HIGHLIGHT;
    this._preparePopup(parameters.dict);
  }
  _util.Util.inherit(HighlightAnnotation, Annotation, {});
  return HighlightAnnotation;
}();
var UnderlineAnnotation = function UnderlineAnnotationClosure() {
  function UnderlineAnnotation(parameters) {
    Annotation.call(this, parameters);
    this.data.annotationType = _util.AnnotationType.UNDERLINE;
    this._preparePopup(parameters.dict);
  }
  _util.Util.inherit(UnderlineAnnotation, Annotation, {});
  return UnderlineAnnotation;
}();
var SquigglyAnnotation = function SquigglyAnnotationClosure() {
  function SquigglyAnnotation(parameters) {
    Annotation.call(this, parameters);
    this.data.annotationType = _util.AnnotationType.SQUIGGLY;
    this._preparePopup(parameters.dict);
  }
  _util.Util.inherit(SquigglyAnnotation, Annotation, {});
  return SquigglyAnnotation;
}();
var StrikeOutAnnotation = function StrikeOutAnnotationClosure() {
  function StrikeOutAnnotation(parameters) {
    Annotation.call(this, parameters);
    this.data.annotationType = _util.AnnotationType.STRIKEOUT;
    this._preparePopup(parameters.dict);
  }
  _util.Util.inherit(StrikeOutAnnotation, Annotation, {});
  return StrikeOutAnnotation;
}();
var FileAttachmentAnnotation = function FileAttachmentAnnotationClosure() {
  function FileAttachmentAnnotation(parameters) {
    Annotation.call(this, parameters);
    var file = new _obj.FileSpec(parameters.dict.get('FS'), parameters.xref);
    this.data.annotationType = _util.AnnotationType.FILEATTACHMENT;
    this.data.file = file.serializable;
    this._preparePopup(parameters.dict);
  }
  _util.Util.inherit(FileAttachmentAnnotation, Annotation, {});
  return FileAttachmentAnnotation;
}();
exports.Annotation = Annotation;
exports.AnnotationBorderStyle = AnnotationBorderStyle;
exports.AnnotationFactory = AnnotationFactory;