# Virtual Background on stream effects

> Guide: https://ai.google.dev/edge/mediapipe/solutions/vision/image_segmenter
> API reference: https://ai.google.dev/edge/api/mediapipe/js/tasks-vision.imagesegmenter#imagesegmenter_class

#### SIMD and non-SIMD

How to test on SIMD:
1. Go to chrome://flags/
2. Search for SIMD flag
3. Enable WebAssembly SIMD support(Enables support for the WebAssembly SIMD proposal).
4. Reopen Google Chrome

More details:
- [WebAssembly](https://webassembly.org/)
- [WebAssembly SIMD](https://github.com/WebAssembly/simd)
- [TFLite](https://blog.tensorflow.org/2020/07/accelerating-tensorflow-lite-xnnpack-integration.html)
