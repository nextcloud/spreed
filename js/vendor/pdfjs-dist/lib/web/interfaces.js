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

var _createClass = function () { function defineProperties(target, props) { for (var i = 0; i < props.length; i++) { var descriptor = props[i]; descriptor.enumerable = descriptor.enumerable || false; descriptor.configurable = true; if ("value" in descriptor) descriptor.writable = true; Object.defineProperty(target, descriptor.key, descriptor); } } return function (Constructor, protoProps, staticProps) { if (protoProps) defineProperties(Constructor.prototype, protoProps); if (staticProps) defineProperties(Constructor, staticProps); return Constructor; }; }();

function _classCallCheck(instance, Constructor) { if (!(instance instanceof Constructor)) { throw new TypeError("Cannot call a class as a function"); } }

var IPDFLinkService = function () {
  function IPDFLinkService() {
    _classCallCheck(this, IPDFLinkService);
  }

  _createClass(IPDFLinkService, [{
    key: 'navigateTo',
    value: function navigateTo(dest) {}
  }, {
    key: 'getDestinationHash',
    value: function getDestinationHash(dest) {}
  }, {
    key: 'getAnchorUrl',
    value: function getAnchorUrl(hash) {}
  }, {
    key: 'setHash',
    value: function setHash(hash) {}
  }, {
    key: 'executeNamedAction',
    value: function executeNamedAction(action) {}
  }, {
    key: 'onFileAttachmentAnnotation',
    value: function onFileAttachmentAnnotation(_ref) {
      var id = _ref.id,
          filename = _ref.filename,
          content = _ref.content;
    }
  }, {
    key: 'cachePageRef',
    value: function cachePageRef(pageNum, pageRef) {}
  }, {
    key: 'page',
    get: function get() {},
    set: function set(value) {}
  }]);

  return IPDFLinkService;
}();

var IPDFHistory = function () {
  function IPDFHistory() {
    _classCallCheck(this, IPDFHistory);
  }

  _createClass(IPDFHistory, [{
    key: 'forward',
    value: function forward() {}
  }, {
    key: 'back',
    value: function back() {}
  }, {
    key: 'push',
    value: function push(params) {}
  }, {
    key: 'updateNextHashParam',
    value: function updateNextHashParam(hash) {}
  }]);

  return IPDFHistory;
}();

var IRenderableView = function () {
  function IRenderableView() {
    _classCallCheck(this, IRenderableView);
  }

  _createClass(IRenderableView, [{
    key: 'draw',
    value: function draw() {}
  }, {
    key: 'resume',
    value: function resume() {}
  }, {
    key: 'renderingId',
    get: function get() {}
  }, {
    key: 'renderingState',
    get: function get() {}
  }]);

  return IRenderableView;
}();

var IPDFTextLayerFactory = function () {
  function IPDFTextLayerFactory() {
    _classCallCheck(this, IPDFTextLayerFactory);
  }

  _createClass(IPDFTextLayerFactory, [{
    key: 'createTextLayerBuilder',
    value: function createTextLayerBuilder(textLayerDiv, pageIndex, viewport) {
      var enhanceTextSelection = arguments.length > 3 && arguments[3] !== undefined ? arguments[3] : false;
    }
  }]);

  return IPDFTextLayerFactory;
}();

var IPDFAnnotationLayerFactory = function () {
  function IPDFAnnotationLayerFactory() {
    _classCallCheck(this, IPDFAnnotationLayerFactory);
  }

  _createClass(IPDFAnnotationLayerFactory, [{
    key: 'createAnnotationLayerBuilder',
    value: function createAnnotationLayerBuilder(pageDiv, pdfPage) {
      var renderInteractiveForms = arguments.length > 2 && arguments[2] !== undefined ? arguments[2] : false;
      var l10n = arguments.length > 3 && arguments[3] !== undefined ? arguments[3] : undefined;
    }
  }]);

  return IPDFAnnotationLayerFactory;
}();

var IL10n = function () {
  function IL10n() {
    _classCallCheck(this, IL10n);
  }

  _createClass(IL10n, [{
    key: 'getDirection',
    value: function getDirection() {}
  }, {
    key: 'get',
    value: function get(key, args, fallback) {}
  }, {
    key: 'translate',
    value: function translate(element) {}
  }]);

  return IL10n;
}();