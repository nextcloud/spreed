### [2.0.0](https://github.com/marionettejs/backbone.radio/releases/tag/2.0.0)

- Updated Backbone and Underscore version ranges.
- Moved Backbone and Underscore to peerDependencies.

### [2.0.0-pre.2](https://github.com/marionettejs/backbone.radio/releases/tag/2.0.0-pre.2)

- Updated Backbone and Underscore version ranges.

### [2.0.0-pre.1](https://github.com/marionettejs/backbone.radio/releases/tag/2.0.0-pre.1)

- Moved Backbone and Underscore to peerDependencies.

### [1.0.5](https://github.com/marionettejs/backbone.radio/releases/tag/1.0.5)

- Updated Backbone dep to allow v1.3.3

### [1.0.4](https://github.com/marionettejs/backbone.radio/releases/tag/1.0.4)

- **Bug fix**: The UMD generated from rollup was setting `global` to `undefined`.

### [1.0.3](https://github.com/marionettejs/backbone.radio/releases/tag/1.0.3)

- Updated Backbone dep to allow v1.3.2

### [1.0.2](https://github.com/marionettejs/backbone.radio/releases/tag/1.0.2)

- Updated Backbone dep to allow v1.2.3

### [1.0.1](https://github.com/marionettejs/backbone.radio/releases/tag/1.0.1)

- Updated Backbone dep to allow v1.2.2

### [1.0.0](https://github.com/jmeas/backbone.radio/releases/tag/v1.0.0)

- **Breaking change**: Commands have been removed. ([see explanation](https://github.com/marionettejs/backbone.radio/pull/221#issuecomment-104782925))

### [0.9.1](https://github.com/jmeas/backbone.radio/releases/tag/v0.9.1)

- **Refactor**: Structure and build using babel-boilerplate
- Update Underscore and Backbone dependencies to 1.8.3 and 1.2.1 respectively to match Marionette.

### [0.9.0](https://github.com/jmeas/backbone.radio/releases/tag/v0.9.0)

- **Breaking change**: Space-separated requests no longer return an Array. Instead, an Object is returned.
  ```js
  // old
  myChannel.request('thingOne thingTwo');
  // => [replyOne, replyTwo]

  // new
  myChannel.request('thingOne thingTwo');
  // => { thingOne: replyOne, thingTwo: replyTwo }
  ```

- **New feature**: `Radio.reset()` is now a top-level API method that can be used to reset a channel, or all channels. Do note that channels continue to have their own `reset` method.
- **New feature**: `Radio.debugLog()` is now exposed...go forth and customize how Radio logs potential errors!

### [0.8.2](https://github.com/jmeas/backbone.radio/releases/tag/v0.8.2)

- **Refactor**: A small refactor to support Underscore 1.4.4 (the lowest version that Marionette supports)

### [0.8.1](https://github.com/jmeas/backbone.radio/releases/tag/v0.8.1)

- **Bug fix**: Fixes bug where `stopComplying` and `stopReplying` would not remove the correct
  callbacks in certain situations

### [0.8.0](https://github.com/jmeas/backbone.radio/releases/tag/v0.8.0)

- **Feature**: DEBUG now warns when an already-registered Command or Request is overwritten
- **Feature**: `stopComplying` and `stopReplying` now accept the same arguments as `off`

### [0.7.2](https://github.com/jmeas/backbone.radio/releases/tag/v0.7.2)

- Corrects Underscore dependency in bower.json.

### [0.7.1](https://github.com/jmeas/backbone.radio/releases/tag/v0.7.1)

- Corrects Underscore dependency.

### [0.7.0](https://github.com/jmeas/backbone.radio/releases/tag/v0.7.0)

- **Feature**: All API methods of Commands ands Requests now support the space-separated syntax.
- **Enhancement**: Only Channels created through Radio's factory method will register themselves on the internal
  store of Channels
- **Enhancement**: Callback execution has been optimized

### [0.6.0](https://github.com/jmeas/backbone.radio/releases/tag/v0.6.0)

*This update is not backwards compatible.*

- **Feature:** `channelName` is now a public property on each Channel.
- **Feature:** Requests and Commands can now have `"default"` handlers which will be called when the specified event isn't registered.
- **API Change:** The convenience connectX methods have been removed. In their place, the object syntax can be used for registering
  multiple events on channels. This makes the API of Radio more consistent with Backbone.Events. For instance,

  ```js
  myChannel.reply({
    oneRequest: myCallback,
    anotherRequest: myCallback
  }, myContext);
  ```

### [0.5.2](https://github.com/jmeas/backbone.radio/releases/tag/v0.5.2)

- Fixes a bug where the top-level API would not pass the correct arguments to the underlying methods.

### [0.5.1](https://github.com/jmeas/backbone.radio/releases/tag/v0.5.1)

- Fixes Radio.VERSION in the built library

### [0.5.0](https://github.com/jmeas/backbone.radio/releases/tag/v0.5.0)

- Commands.react has been renamed to Commands.comply

### [0.4.1](https://github.com/jmeas/backbone.radio/releases/tag/v0.4.1)

- The Channel convenience methods no longer bind the context, instead deferring that
responsibility to the wrapped methods themselves. This aids in stack traces and gives you
the ability to unregister the methods individually.

### [0.4.0](https://github.com/jmeas/backbone.radio/releases/tag/v0.4.0)

- Debug mode now informs you when you attempt to unregister an event that was never registered. This is to help prevent memory leaks.
- `respond` has been renamed to `reply`
- More methods now return `this`, making the API more consistent internally, and with Backbone.Events

### [0.3.0](https://github.com/jmeas/backbone.radio/releases/tag/v0.3.0)

- More test coverage
- Tests completely rewritten
- Numerous bug fixes; more work on the library
