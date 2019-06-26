# Backbone.Radio

[![Travis Build Status](http://img.shields.io/travis/marionettejs/backbone.radio.svg?style=flat)](https://travis-ci.org/marionettejs/backbone.radio)
[![Coverage](http://img.shields.io/codeclimate/coverage/github/marionettejs/backbone.radio.svg?style=flat)](https://codeclimate.com/github/marionettejs/backbone.radio)
[![Dependency Status](http://img.shields.io/david/marionettejs/backbone.radio.svg?style=flat)](https://david-dm.org/marionettejs/backbone.radio)
[![Gitter chat room](https://img.shields.io/badge/gitter-backbone.radio-brightgreen.svg?style=flat)](https://gitter.im/marionettejs/backbone.radio)


Backbone.Radio provides additional messaging patterns for Backbone applications.

Backbone includes an event system, Backbone.Events, which is an implementation of the publish-subscribe pattern. Pub-sub is by far the most
common event pattern in client-side applications, and for good reason: it is incredibly useful. It should also be familiar to web developers
in particular, because the DOM relies heavily on pub-sub. Consider, for instance, registering a handler on an element's `click` event. This isn't
so much different than listening to a Model's `change` event, as both of these situations are using pub-sub.

Backbone.Radio adds two additional messaging-related features. The first is Requests, an implementation of the request-reply pattern. Request-reply
should also be familiar to web developers, as it's the messaging pattern that backs HTTP communications. The other feature are Channels: explicit
namespaces to your communications.

## Installation

Clone this repository or install via [Bower](http://bower.io/) or [npm](https://www.npmjs.org/).

```
bower install backbone.radio
npm install backbone.radio
```

You must also ensure that Backbone.Radio's dependencies on Underscore (or Lodash) and Backbone are installed.

## Documentation

- [Getting Started](#getting-started)
  - [Backbone.Events](#backboneevents)
  - [Radio.Requests](#backboneradiorequests)
  - [Channels](#channels)
  - [Using With Marionette](#using-with-marionette)
- [API](#api)
  - [Radio.Requests](#requests)
  - [Channel](#channel)
  - [Radio](#radio)
  - [Top-level API](#top-level-api)

## Getting Started

### Backbone.Events

Anyone who has used Backbone should be quite familiar with Backbone.Events. Backbone.Events is what facilitates
communications between objects in your application. The quintessential example of this is listening in on a
Model's change event.

```js
// Listen in on a model's change events
this.listenTo(someModel, 'change', myCallback);

// Later on, the model triggers a change event when it has been changed
someModel.trigger('change');
```

Let's look at a diagram for Backbone.Events:

<p align='center'>
  <img src='https://cloud.githubusercontent.com/assets/10248067/11762943/5a927e54-a0bd-11e5-8aa5-e0fafae0e559.png' alt='Backbone.Events diagram'>
</p>

It goes without saying that Backbone.Events is incredibly useful when you mix it into instances of Classes. But what
if you had a standalone Object with an instance of Backbone.Events on it? This gives you a powerful message bus to utilize.

```js
// Create a message bus
var myBus = _.extend({}, Backbone.Events);

// Listen in on the message bus
this.listenTo(myBus, 'some:event', myCallback);

// Trigger an event on the bus
myBus.trigger('some:event');
```

As long as there was an easy way to access this message bus throughout your entire application, then you would have a central
place to store a collection of events.  This is the idea behind Channels. But before we go more into that, let's take a look at Requests.

### Backbone.Radio.Requests

Requests is similar to Events in that it's another event system. And it has a similar API, too. For this reason, you *could* mix
it into an object.

```js
_.extend(myView, Backbone.Radio.Requests);
```

Although this works, I wouldn't recommend it. Requests are most useful, I think, when they're used with a Channel.

Perhaps the biggest difference between Events and Requests is that Requests have *intention*. Unlike Events, which notify
nothing in particular about an occurrence, Requests are asking for a very specific thing to occur. As a consequence of this,
requests are 'one-to-one,' which means that you cannot have multiple 'listeners' to a single request.

Let's look at a basic example.

```js
// Set up an object to reply to a request. In this case, whether or not its visible.
myObject.reply('visible', this.isVisible);

// Get whether it's visible or not.
var isViewVisible = myObject.request('visible');
```

The handler in `reply` can either return a flat value, like `true` or `false`, or a function to be executed. Either way, the value is sent back to
the requester.

Here's a diagram of the Requests pattern:

<p align='center'>
  <img src='https://cloud.githubusercontent.com/assets/10248067/11762945/5c302a36-a0bd-11e5-8e4e-0eee7cacbef1.png' alt='Backbone.Requests diagram'>
</p>

Although the name is 'Requests,' you can just as easily request information as you can request that an action be completed. Just like HTTP,
where you can both make GET requests for information, or DELETE requests to order than a resource be deleted, Requests can be used for a variety
of purposes.

One thing to note is that this pattern is **identical** to a simple method call. One can just as easily rewrite the above example as:

```js
// Set up a method...
myObject.isVisible = function() {
  return this.viewIsVisible;
}

// Call that method
var isViewVisible = myObject.isVisible();
```

This is why mixing Requests into something like a View or Model does not make much sense. If you have access to the View or Model, then
you might as well just use methods.

### Channels

The real draw of Backbone.Radio are Channels. A Channel is simply an object that has Backbone.Events and Radio.Requests mixed into it:
it's a standalone message bus comprised of both systems.

Getting a handle of a Channel is easy.

```js
// Get a reference to the channel named 'user'
var userChannel = Backbone.Radio.channel('user');
```

Once you've got a channel, you can attach handlers to it.

```js
userChannel.on('some:event', function() {
  console.log('An event has happened!');
});

userChannel.reply('some:request', 'food is good');
```

You can also use the 'trigger' methods on the Channel.

```js
userChannel.trigger('some:event');

userChannel.request('some:request');
```

You can have as many channels as you'd like

```js
// Maybe you have a channel for the profile section of your app
var profileChannel = Backbone.Radio.channel('profile');

// And another one for settings
var settingsChannel = Backbone.Radio.channel('settings');
```

The whole point of Channels is that they provide a way to explicitly namespace events in your application, and a means to easily access
any of those namespaces.

### Using With Marionette

[Marionette](https://github.com/marionettejs/backbone.marionette) does not use Radio by default, although it will in the next major release: v3. However, you can use Radio today by including a small shim after you load Marionette, but before you load your application's code. To get the shim, refer to [this Gist](https://gist.github.com/jmeas/7992474cdb1c5672d88b).

## API

Like Backbone.Events, **all** of the following methods support both the object-syntax and space-separated syntax. For the sake of brevity,
I only provide examples for these alternate syntaxes in the most common use cases.

### Requests

#### `request( requestName [, args...] )`

Make a request for `requestName`. Optionally pass arguments to send along to the callback. Returns the reply, if one
exists. If there is no reply registered then `undefined` will be returned.

You can make multiple requests at once by using the space-separated syntax.

```js
myChannel.request('requestOne requestTwo');
```

When using the space-separated syntax, the responses will be returned to you as an object, where
the keys are the name of the request, and the values are the replies.

#### `reply( requestName, callback [, context] )`

Register a handler for `requestName` on this object. `callback` will be executed whenever the request is made. Optionally
pass a `context` for the callback, defaulting to `this`.

To register a default handler for Requests use the `default` requestName. The unhandled `requestName` will be passed as the first argument.

```js
myChannel.reply('default', function(requestName) {
  console.log('No reply exists for this request: ' + requestName);
});

// This will be handled by the default request
myChannel.request('someUnhandledRequest');
```

To register multiple requests at once you may also pass in a hash.

```js
// Connect all of the replies at once
myChannel.reply({
  'some:request': myCallback,
  'some:other:request': someOtherCallback
}, context);
```

Returns the instance of Requests.

#### `replyOnce( requestName, callback [, context] )`

Register a handler for `requestName` that will only be called a single time.

Like `reply`, you may also pass a hash of replies to register many at once. Refer to the `reply` documentation above
for an example.

Returns the instance of Requests.

#### `stopReplying( [requestName] [, callback] [, context] )`

If `context` is passed, then all replies with that context will be removed from the object. If `callback` is
passed then all requests with that callback will be removed. If `requestName` is passed then this method will
remove that reply. If no arguments are passed then all replies are removed from the object.

You may also pass a hash of replies or space-separated replies to remove many at once.

Returns the instance of Requests.

### Channel

#### `channelName`

The name of the channel.

#### `reset()`

Destroy all handlers from Backbone.Events and Radio.Requests from the channel. Returns the channel.

### Radio

#### `channel( channelName )`

Get a reference to a channel by name. If a name is not provided an Error will be thrown.

```js
var authChannel = Backbone.Radio.channel('auth');
```

#### `DEBUG`

This is a Boolean property. Setting it to `true` will cause console warnings to be issued
whenever you interact with a `request` that isn't registered. This is useful in development when you want to
ensure that you've got your event names in order.

```js
// Turn on debug mode
Backbone.Radio.DEBUG = true;

// This will log a warning to the console if it goes unhandled
myChannel.request('show:view');

// Likewise, this will too, helping to prevent memory leaks
myChannel.stopReplying('startTime');
```

#### `debugLog(warning, eventName, channelName)`

A function executed whenever an unregistered request is interacted with on a Channel. Only
called when `DEBUG` is set to `true`. By overriding this you could, for instance, make unhandled
events throw Errors.

The warning is a string describing the type of problem, such as:

> Attempted to remove the unregistered request

while the `eventName` and `channelName` are what you would expect.

#### `tuneIn( channelName )`

Tuning into a Channel is another useful tool for debugging. It passes all
triggers and requests made on the channel to

[`Radio.log`](https://github.com/jmeas/backbone.radio#log-channelname-eventname--args-).
Returns `Backbone.Radio`.

```js
Backbone.Radio.tuneIn('calendar');
```

#### `tuneOut( channelName )`

Once you're done tuning in you can call `tuneOut` to stop the logging. Returns `Backbone.Radio`.

```js
Backbone.Radio.tuneOut('calendar');
```

#### `log( channelName, eventName [, args...] )`

When tuned into a Channel, this method will be called for all activity on
a channel. The default implementation is to `console.log` the following message:

```js
'[channelName] "eventName" args1 arg2 arg3...'
```

where args are all of the arguments passed with the message. It is exposed so that you
may overwrite it with your own logging message if you wish.

### 'Top-level' API

If you'd like to execute a method on a channel, yet you don't need to keep a handle of the
channel around, you can do so with the proxy functions directly on the `Backbone.Radio` object.

```js
// Trigger 'some:event' on the settings channel
Backbone.Radio.trigger('settings', 'some:event');
```

All of the methods for both messaging systems are available from the top-level API.

#### `reset( [channelName] )`

You can also reset a single channel, or all Channels, from the `Radio` object directly. Pass a
`channelName` to reset just that specific channel, or call the method without any arguments
to reset every channel.

```js
// Reset all channels
Radio.reset();
```
