/*! jQuery UI - v1.10.3 - 2013-05-03
* http://jqueryui.com
* Includes: jquery.ui.core.js, jquery.ui.widget.js, jquery.ui.mouse.js, jquery.ui.draggable.js, jquery.ui.droppable.js, jquery.ui.resizable.js, jquery.ui.selectable.js, jquery.ui.sortable.js, jquery.ui.effect.js, jquery.ui.accordion.js, jquery.ui.autocomplete.js, jquery.ui.button.js, jquery.ui.datepicker.js, jquery.ui.dialog.js, jquery.ui.effect-blind.js, jquery.ui.effect-bounce.js, jquery.ui.effect-clip.js, jquery.ui.effect-drop.js, jquery.ui.effect-explode.js, jquery.ui.effect-fade.js, jquery.ui.effect-fold.js, jquery.ui.effect-highlight.js, jquery.ui.effect-pulsate.js, jquery.ui.effect-scale.js, jquery.ui.effect-shake.js, jquery.ui.effect-slide.js, jquery.ui.effect-transfer.js, jquery.ui.menu.js, jquery.ui.position.js, jquery.ui.progressbar.js, jquery.ui.slider.js, jquery.ui.spinner.js, jquery.ui.tabs.js, jquery.ui.tooltip.js
* Copyright 2013 jQuery Foundation and other contributors; Licensed MIT */
(function( $, undefined ) {

var uuid = 0,
	runiqueId = /^ui-id-\d+$/;

// $.ui might exist from components with no dependencies, e.g., $.ui.position
$.ui = $.ui || {};

$.extend( $.ui, {
	version: "1.10.3",

	keyCode: {
		BACKSPACE: 8,
		COMMA: 188,
		DELETE: 46,
		DOWN: 40,
		END: 35,
		ENTER: 13,
		ESCAPE: 27,
		HOME: 36,
		LEFT: 37,
		NUMPAD_ADD: 107,
		NUMPAD_DECIMAL: 110,
		NUMPAD_DIVIDE: 111,
		NUMPAD_ENTER: 108,
		NUMPAD_MULTIPLY: 106,
		NUMPAD_SUBTRACT: 109,
		PAGE_DOWN: 34,
		PAGE_UP: 33,
		PERIOD: 190,
		RIGHT: 39,
		SPACE: 32,
		TAB: 9,
		UP: 38
	}
});

// plugins
$.fn.extend({
	focus: (function( orig ) {
		return function( delay, fn ) {
			return typeof delay === "number" ?
				this.each(function() {
					var elem = this;
					setTimeout(function() {
						$( elem ).focus();
						if ( fn ) {
							fn.call( elem );
						}
					}, delay );
				}) :
				orig.apply( this, arguments );
		};
	})( $.fn.focus ),

	scrollParent: function() {
		var scrollParent;
		if (($.ui.ie && (/(static|relative)/).test(this.css("position"))) || (/absolute/).test(this.css("position"))) {
			scrollParent = this.parents().filter(function() {
				return (/(relative|absolute|fixed)/).test($.css(this,"position")) && (/(auto|scroll)/).test($.css(this,"overflow")+$.css(this,"overflow-y")+$.css(this,"overflow-x"));
			}).eq(0);
		} else {
			scrollParent = this.parents().filter(function() {
				return (/(auto|scroll)/).test($.css(this,"overflow")+$.css(this,"overflow-y")+$.css(this,"overflow-x"));
			}).eq(0);
		}

		return (/fixed/).test(this.css("position")) || !scrollParent.length ? $(document) : scrollParent;
	},

	zIndex: function( zIndex ) {
		if ( zIndex !== undefined ) {
			return this.css( "zIndex", zIndex );
		}

		if ( this.length ) {
			var elem = $( this[ 0 ] ), position, value;
			while ( elem.length && elem[ 0 ] !== document ) {
				// Ignore z-index if position is set to a value where z-index is ignored by the browser
				// This makes behavior of this function consistent across browsers
				// WebKit always returns auto if the element is positioned
				position = elem.css( "position" );
				if ( position === "absolute" || position === "relative" || position === "fixed" ) {
					// IE returns 0 when zIndex is not specified
					// other browsers return a string
					// we ignore the case of nested elements with an explicit value of 0
					// <div style="z-index: -10;"><div style="z-index: 0;"></div></div>
					value = parseInt( elem.css( "zIndex" ), 10 );
					if ( !isNaN( value ) && value !== 0 ) {
						return value;
					}
				}
				elem = elem.parent();
			}
		}

		return 0;
	},

	uniqueId: function() {
		return this.each(function() {
			if ( !this.id ) {
				this.id = "ui-id-" + (++uuid);
			}
		});
	},

	removeUniqueId: function() {
		return this.each(function() {
			if ( runiqueId.test( this.id ) ) {
				$( this ).removeAttr( "id" );
			}
		});
	}
});

// selectors
function focusable( element, isTabIndexNotNaN ) {
	var map, mapName, img,
		nodeName = element.nodeName.toLowerCase();
	if ( "area" === nodeName ) {
		map = element.parentNode;
		mapName = map.name;
		if ( !element.href || !mapName || map.nodeName.toLowerCase() !== "map" ) {
			return false;
		}
		img = $( "img[usemap=#" + mapName + "]" )[0];
		return !!img && visible( img );
	}
	return ( /input|select|textarea|button|object/.test( nodeName ) ?
		!element.disabled :
		"a" === nodeName ?
			element.href || isTabIndexNotNaN :
			isTabIndexNotNaN) &&
		// the element and all of its ancestors must be visible
		visible( element );
}

function visible( element ) {
	return $.expr.filters.visible( element ) &&
		!$( element ).parents().addBack().filter(function() {
			return $.css( this, "visibility" ) === "hidden";
		}).length;
}

$.extend( $.expr[ ":" ], {
	data: $.expr.createPseudo ?
		$.expr.createPseudo(function( dataName ) {
			return function( elem ) {
				return !!$.data( elem, dataName );
			};
		}) :
		// support: jQuery <1.8
		function( elem, i, match ) {
			return !!$.data( elem, match[ 3 ] );
		},

	focusable: function( element ) {
		return focusable( element, !isNaN( $.attr( element, "tabindex" ) ) );
	},

	tabbable: function( element ) {
		var tabIndex = $.attr( element, "tabindex" ),
			isTabIndexNaN = isNaN( tabIndex );
		return ( isTabIndexNaN || tabIndex >= 0 ) && focusable( element, !isTabIndexNaN );
	}
});

// support: jQuery <1.8
if ( !$( "<a>" ).outerWidth( 1 ).jquery ) {
	$.each( [ "Width", "Height" ], function( i, name ) {
		var side = name === "Width" ? [ "Left", "Right" ] : [ "Top", "Bottom" ],
			type = name.toLowerCase(),
			orig = {
				innerWidth: $.fn.innerWidth,
				innerHeight: $.fn.innerHeight,
				outerWidth: $.fn.outerWidth,
				outerHeight: $.fn.outerHeight
			};

		function reduce( elem, size, border, margin ) {
			$.each( side, function() {
				size -= parseFloat( $.css( elem, "padding" + this ) ) || 0;
				if ( border ) {
					size -= parseFloat( $.css( elem, "border" + this + "Width" ) ) || 0;
				}
				if ( margin ) {
					size -= parseFloat( $.css( elem, "margin" + this ) ) || 0;
				}
			});
			return size;
		}

		$.fn[ "inner" + name ] = function( size ) {
			if ( size === undefined ) {
				return orig[ "inner" + name ].call( this );
			}

			return this.each(function() {
				$( this ).css( type, reduce( this, size ) + "px" );
			});
		};

		$.fn[ "outer" + name] = function( size, margin ) {
			if ( typeof size !== "number" ) {
				return orig[ "outer" + name ].call( this, size );
			}

			return this.each(function() {
				$( this).css( type, reduce( this, size, true, margin ) + "px" );
			});
		};
	});
}

// support: jQuery <1.8
if ( !$.fn.addBack ) {
	$.fn.addBack = function( selector ) {
		return this.add( selector == null ?
			this.prevObject : this.prevObject.filter( selector )
		);
	};
}

// support: jQuery 1.6.1, 1.6.2 (http://bugs.jquery.com/ticket/9413)
if ( $( "<a>" ).data( "a-b", "a" ).removeData( "a-b" ).data( "a-b" ) ) {
	$.fn.removeData = (function( removeData ) {
		return function( key ) {
			if ( arguments.length ) {
				return removeData.call( this, $.camelCase( key ) );
			} else {
				return removeData.call( this );
			}
		};
	})( $.fn.removeData );
}





// deprecated
$.ui.ie = !!/msie [\w.]+/.exec( navigator.userAgent.toLowerCase() );

$.support.selectstart = "onselectstart" in document.createElement( "div" );
$.fn.extend({
	disableSelection: function() {
		return this.bind( ( $.support.selectstart ? "selectstart" : "mousedown" ) +
			".ui-disableSelection", function( event ) {
				event.preventDefault();
			});
	},

	enableSelection: function() {
		return this.unbind( ".ui-disableSelection" );
	}
});

$.extend( $.ui, {
	// $.ui.plugin is deprecated. Use $.widget() extensions instead.
	plugin: {
		add: function( module, option, set ) {
			var i,
				proto = $.ui[ module ].prototype;
			for ( i in set ) {
				proto.plugins[ i ] = proto.plugins[ i ] || [];
				proto.plugins[ i ].push( [ option, set[ i ] ] );
			}
		},
		call: function( instance, name, args ) {
			var i,
				set = instance.plugins[ name ];
			if ( !set || !instance.element[ 0 ].parentNode || instance.element[ 0 ].parentNode.nodeType === 11 ) {
				return;
			}

			for ( i = 0; i < set.length; i++ ) {
				if ( instance.options[ set[ i ][ 0 ] ] ) {
					set[ i ][ 1 ].apply( instance.element, args );
				}
			}
		}
	},

	// only used by resizable
	hasScroll: function( el, a ) {

		//If overflow is hidden, the element might have extra content, but the user wants to hide it
		if ( $( el ).css( "overflow" ) === "hidden") {
			return false;
		}

		var scroll = ( a && a === "left" ) ? "scrollLeft" : "scrollTop",
			has = false;

		if ( el[ scroll ] > 0 ) {
			return true;
		}

		// TODO: determine which cases actually cause this to happen
		// if the element doesn't have the scroll set, see if it's possible to
		// set the scroll
		el[ scroll ] = 1;
		has = ( el[ scroll ] > 0 );
		el[ scroll ] = 0;
		return has;
	}
});

})( jQuery );

(function( $, undefined ) {

var uuid = 0,
	slice = Array.prototype.slice,
	_cleanData = $.cleanData;
$.cleanData = function( elems ) {
	for ( var i = 0, elem; (elem = elems[i]) != null; i++ ) {
		try {
			$( elem ).triggerHandler( "remove" );
		// http://bugs.jquery.com/ticket/8235
		} catch( e ) {}
	}
	_cleanData( elems );
};

$.widget = function( name, base, prototype ) {
	var fullName, existingConstructor, constructor, basePrototype,
		// proxiedPrototype allows the provided prototype to remain unmodified
		// so that it can be used as a mixin for multiple widgets (#8876)
		proxiedPrototype = {},
		namespace = name.split( "." )[ 0 ];

	name = name.split( "." )[ 1 ];
	fullName = namespace + "-" + name;

	if ( !prototype ) {
		prototype = base;
		base = $.Widget;
	}

	// create selector for plugin
	$.expr[ ":" ][ fullName.toLowerCase() ] = function( elem ) {
		return !!$.data( elem, fullName );
	};

	$[ namespace ] = $[ namespace ] || {};
	existingConstructor = $[ namespace ][ name ];
	constructor = $[ namespace ][ name ] = function( options, element ) {
		// allow instantiation without "new" keyword
		if ( !this._createWidget ) {
			return new constructor( options, element );
		}

		// allow instantiation without initializing for simple inheritance
		// must use "new" keyword (the code above always passes args)
		if ( arguments.length ) {
			this._createWidget( options, element );
		}
	};
	// extend with the existing constructor to carry over any static properties
	$.extend( constructor, existingConstructor, {
		version: prototype.version,
		// copy the object used to create the prototype in case we need to
		// redefine the widget later
		_proto: $.extend( {}, prototype ),
		// track widgets that inherit from this widget in case this widget is
		// redefined after a widget inherits from it
		_childConstructors: []
	});

	basePrototype = new base();
	// we need to make the options hash a property directly on the new instance
	// otherwise we'll modify the options hash on the prototype that we're
	// inheriting from
	basePrototype.options = $.widget.extend( {}, basePrototype.options );
	$.each( prototype, function( prop, value ) {
		if ( !$.isFunction( value ) ) {
			proxiedPrototype[ prop ] = value;
			return;
		}
		proxiedPrototype[ prop ] = (function() {
			var _super = function() {
					return base.prototype[ prop ].apply( this, arguments );
				},
				_superApply = function( args ) {
					return base.prototype[ prop ].apply( this, args );
				};
			return function() {
				var __super = this._super,
					__superApply = this._superApply,
					returnValue;

				this._super = _super;
				this._superApply = _superApply;

				returnValue = value.apply( this, arguments );

				this._super = __super;
				this._superApply = __superApply;

				return returnValue;
			};
		})();
	});
	constructor.prototype = $.widget.extend( basePrototype, {
		// TODO: remove support for widgetEventPrefix
		// always use the name + a colon as the prefix, e.g., draggable:start
		// don't prefix for widgets that aren't DOM-based
		widgetEventPrefix: existingConstructor ? basePrototype.widgetEventPrefix : name
	}, proxiedPrototype, {
		constructor: constructor,
		namespace: namespace,
		widgetName: name,
		widgetFullName: fullName
	});

	// If this widget is being redefined then we need to find all widgets that
	// are inheriting from it and redefine all of them so that they inherit from
	// the new version of this widget. We're essentially trying to replace one
	// level in the prototype chain.
	if ( existingConstructor ) {
		$.each( existingConstructor._childConstructors, function( i, child ) {
			var childPrototype = child.prototype;

			// redefine the child widget using the same prototype that was
			// originally used, but inherit from the new version of the base
			$.widget( childPrototype.namespace + "." + childPrototype.widgetName, constructor, child._proto );
		});
		// remove the list of existing child constructors from the old constructor
		// so the old child constructors can be garbage collected
		delete existingConstructor._childConstructors;
	} else {
		base._childConstructors.push( constructor );
	}

	$.widget.bridge( name, constructor );
};

$.widget.extend = function( target ) {
	var input = slice.call( arguments, 1 ),
		inputIndex = 0,
		inputLength = input.length,
		key,
		value;
	for ( ; inputIndex < inputLength; inputIndex++ ) {
		for ( key in input[ inputIndex ] ) {
			value = input[ inputIndex ][ key ];
			if ( input[ inputIndex ].hasOwnProperty( key ) && value !== undefined ) {
				// Clone objects
				if ( $.isPlainObject( value ) ) {
					target[ key ] = $.isPlainObject( target[ key ] ) ?
						$.widget.extend( {}, target[ key ], value ) :
						// Don't extend strings, arrays, etc. with objects
						$.widget.extend( {}, value );
				// Copy everything else by reference
				} else {
					target[ key ] = value;
				}
			}
		}
	}
	return target;
};

$.widget.bridge = function( name, object ) {
	var fullName = object.prototype.widgetFullName || name;
	$.fn[ name ] = function( options ) {
		var isMethodCall = typeof options === "string",
			args = slice.call( arguments, 1 ),
			returnValue = this;

		// allow multiple hashes to be passed on init
		options = !isMethodCall && args.length ?
			$.widget.extend.apply( null, [ options ].concat(args) ) :
			options;

		if ( isMethodCall ) {
			this.each(function() {
				var methodValue,
					instance = $.data( this, fullName );
				if ( !instance ) {
					return $.error( "cannot call methods on " + name + " prior to initialization; " +
						"attempted to call method '" + options + "'" );
				}
				if ( !$.isFunction( instance[options] ) || options.charAt( 0 ) === "_" ) {
					return $.error( "no such method '" + options + "' for " + name + " widget instance" );
				}
				methodValue = instance[ options ].apply( instance, args );
				if ( methodValue !== instance && methodValue !== undefined ) {
					returnValue = methodValue && methodValue.jquery ?
						returnValue.pushStack( methodValue.get() ) :
						methodValue;
					return false;
				}
			});
		} else {
			this.each(function() {
				var instance = $.data( this, fullName );
				if ( instance ) {
					instance.option( options || {} )._init();
				} else {
					$.data( this, fullName, new object( options, this ) );
				}
			});
		}

		return returnValue;
	};
};

$.Widget = function( /* options, element */ ) {};
$.Widget._childConstructors = [];

$.Widget.prototype = {
	widgetName: "widget",
	widgetEventPrefix: "",
	defaultElement: "<div>",
	options: {
		disabled: false,

		// callbacks
		create: null
	},
	_createWidget: function( options, element ) {
		element = $( element || this.defaultElement || this )[ 0 ];
		this.element = $( element );
		this.uuid = uuid++;
		this.eventNamespace = "." + this.widgetName + this.uuid;
		this.options = $.widget.extend( {},
			this.options,
			this._getCreateOptions(),
			options );

		this.bindings = $();
		this.hoverable = $();
		this.focusable = $();

		if ( element !== this ) {
			$.data( element, this.widgetFullName, this );
			this._on( true, this.element, {
				remove: function( event ) {
					if ( event.target === element ) {
						this.destroy();
					}
				}
			});
			this.document = $( element.style ?
				// element within the document
				element.ownerDocument :
				// element is window or document
				element.document || element );
			this.window = $( this.document[0].defaultView || this.document[0].parentWindow );
		}

		this._create();
		this._trigger( "create", null, this._getCreateEventData() );
		this._init();
	},
	_getCreateOptions: $.noop,
	_getCreateEventData: $.noop,
	_create: $.noop,
	_init: $.noop,

	destroy: function() {
		this._destroy();
		// we can probably remove the unbind calls in 2.0
		// all event bindings should go through this._on()
		this.element
			.unbind( this.eventNamespace )
			// 1.9 BC for #7810
			// TODO remove dual storage
			.removeData( this.widgetName )
			.removeData( this.widgetFullName )
			// support: jquery <1.6.3
			// http://bugs.jquery.com/ticket/9413
			.removeData( $.camelCase( this.widgetFullName ) );
		this.widget()
			.unbind( this.eventNamespace )
			.removeAttr( "aria-disabled" )
			.removeClass(
				this.widgetFullName + "-disabled " +
				"ui-state-disabled" );

		// clean up events and states
		this.bindings.unbind( this.eventNamespace );
		this.hoverable.removeClass( "ui-state-hover" );
		this.focusable.removeClass( "ui-state-focus" );
	},
	_destroy: $.noop,

	widget: function() {
		return this.element;
	},

	option: function( key, value ) {
		var options = key,
			parts,
			curOption,
			i;

		if ( arguments.length === 0 ) {
			// don't return a reference to the internal hash
			return $.widget.extend( {}, this.options );
		}

		if ( typeof key === "string" ) {
			// handle nested keys, e.g., "foo.bar" => { foo: { bar: ___ } }
			options = {};
			parts = key.split( "." );
			key = parts.shift();
			if ( parts.length ) {
				curOption = options[ key ] = $.widget.extend( {}, this.options[ key ] );
				for ( i = 0; i < parts.length - 1; i++ ) {
					curOption[ parts[ i ] ] = curOption[ parts[ i ] ] || {};
					curOption = curOption[ parts[ i ] ];
				}
				key = parts.pop();
				if ( value === undefined ) {
					return curOption[ key ] === undefined ? null : curOption[ key ];
				}
				curOption[ key ] = value;
			} else {
				if ( value === undefined ) {
					return this.options[ key ] === undefined ? null : this.options[ key ];
				}
				options[ key ] = value;
			}
		}

		this._setOptions( options );

		return this;
	},
	_setOptions: function( options ) {
		var key;

		for ( key in options ) {
			this._setOption( key, options[ key ] );
		}

		return this;
	},
	_setOption: function( key, value ) {
		this.options[ key ] = value;

		if ( key === "disabled" ) {
			this.widget()
				.toggleClass( this.widgetFullName + "-disabled ui-state-disabled", !!value )
				.attr( "aria-disabled", value );
			this.hoverable.removeClass( "ui-state-hover" );
			this.focusable.removeClass( "ui-state-focus" );
		}

		return this;
	},

	enable: function() {
		return this._setOption( "disabled", false );
	},
	disable: function() {
		return this._setOption( "disabled", true );
	},

	_on: function( suppressDisabledCheck, element, handlers ) {
		var delegateElement,
			instance = this;

		// no suppressDisabledCheck flag, shuffle arguments
		if ( typeof suppressDisabledCheck !== "boolean" ) {
			handlers = element;
			element = suppressDisabledCheck;
			suppressDisabledCheck = false;
		}

		// no element argument, shuffle and use this.element
		if ( !handlers ) {
			handlers = element;
			element = this.element;
			delegateElement = this.widget();
		} else {
			// accept selectors, DOM elements
			element = delegateElement = $( element );
			this.bindings = this.bindings.add( element );
		}

		$.each( handlers, function( event, handler ) {
			function handlerProxy() {
				// allow widgets to customize the disabled handling
				// - disabled as an array instead of boolean
				// - disabled class as method for disabling individual parts
				if ( !suppressDisabledCheck &&
						( instance.options.disabled === true ||
							$( this ).hasClass( "ui-state-disabled" ) ) ) {
					return;
				}
				return ( typeof handler === "string" ? instance[ handler ] : handler )
					.apply( instance, arguments );
			}

			// copy the guid so direct unbinding works
			if ( typeof handler !== "string" ) {
				handlerProxy.guid = handler.guid =
					handler.guid || handlerProxy.guid || $.guid++;
			}

			var match = event.match( /^(\w+)\s*(.*)$/ ),
				eventName = match[1] + instance.eventNamespace,
				selector = match[2];
			if ( selector ) {
				delegateElement.delegate( selector, eventName, handlerProxy );
			} else {
				element.bind( eventName, handlerProxy );
			}
		});
	},

	_off: function( element, eventName ) {
		eventName = (eventName || "").split( " " ).join( this.eventNamespace + " " ) + this.eventNamespace;
		element.unbind( eventName ).undelegate( eventName );
	},

	_delay: function( handler, delay ) {
		function handlerProxy() {
			return ( typeof handler === "string" ? instance[ handler ] : handler )
				.apply( instance, arguments );
		}
		var instance = this;
		return setTimeout( handlerProxy, delay || 0 );
	},

	_hoverable: function( element ) {
		this.hoverable = this.hoverable.add( element );
		this._on( element, {
			mouseenter: function( event ) {
				$( event.currentTarget ).addClass( "ui-state-hover" );
			},
			mouseleave: function( event ) {
				$( event.currentTarget ).removeClass( "ui-state-hover" );
			}
		});
	},

	_focusable: function( element ) {
		this.focusable = this.focusable.add( element );
		this._on( element, {
			focusin: function( event ) {
				$( event.currentTarget ).addClass( "ui-state-focus" );
			},
			focusout: function( event ) {
				$( event.currentTarget ).removeClass( "ui-state-focus" );
			}
		});
	},

	_trigger: function( type, event, data ) {
		var prop, orig,
			callback = this.options[ type ];

		data = data || {};
		event = $.Event( event );
		event.type = ( type === this.widgetEventPrefix ?
			type :
			this.widgetEventPrefix + type ).toLowerCase();
		// the original event may come from any element
		// so we need to reset the target on the new event
		event.target = this.element[ 0 ];

		// copy original event properties over to the new event
		orig = event.originalEvent;
		if ( orig ) {
			for ( prop in orig ) {
				if ( !( prop in event ) ) {
					event[ prop ] = orig[ prop ];
				}
			}
		}

		this.element.trigger( event, data );
		return !( $.isFunction( callback ) &&
			callback.apply( this.element[0], [ event ].concat( data ) ) === false ||
			event.isDefaultPrevented() );
	}
};

$.each( { show: "fadeIn", hide: "fadeOut" }, function( method, defaultEffect ) {
	$.Widget.prototype[ "_" + method ] = function( element, options, callback ) {
		if ( typeof options === "string" ) {
			options = { effect: options };
		}
		var hasOptions,
			effectName = !options ?
				method :
				options === true || typeof options === "number" ?
					defaultEffect :
					options.effect || defaultEffect;
		options = options || {};
		if ( typeof options === "number" ) {
			options = { duration: options };
		}
		hasOptions = !$.isEmptyObject( options );
		options.complete = callback;
		if ( options.delay ) {
			element.delay( options.delay );
		}
		if ( hasOptions && $.effects && $.effects.effect[ effectName ] ) {
			element[ method ]( options );
		} else if ( effectName !== method && element[ effectName ] ) {
			element[ effectName ]( options.duration, options.easing, callback );
		} else {
			element.queue(function( next ) {
				$( this )[ method ]();
				if ( callback ) {
					callback.call( element[ 0 ] );
				}
				next();
			});
		}
	};
});

})( jQuery );

(function( $, undefined ) {

var mouseHandled = false;
$( document ).mouseup( function() {
	mouseHandled = false;
});

$.widget("ui.mouse", {
	version: "1.10.3",
	options: {
		cancel: "input,textarea,button,select,option",
		distance: 1,
		delay: 0
	},
	_mouseInit: function() {
		var that = this;

		this.element
			.bind("mousedown."+this.widgetName, function(event) {
				return that._mouseDown(event);
			})
			.bind("click."+this.widgetName, function(event) {
				if (true === $.data(event.target, that.widgetName + ".preventClickEvent")) {
					$.removeData(event.target, that.widgetName + ".preventClickEvent");
					event.stopImmediatePropagation();
					return false;
				}
			});

		this.started = false;
	},

	// TODO: make sure destroying one instance of mouse doesn't mess with
	// other instances of mouse
	_mouseDestroy: function() {
		this.element.unbind("."+this.widgetName);
		if ( this._mouseMoveDelegate ) {
			$(document)
				.unbind("mousemove."+this.widgetName, this._mouseMoveDelegate)
				.unbind("mouseup."+this.widgetName, this._mouseUpDelegate);
		}
	},

	_mouseDown: function(event) {
		// don't let more than one widget handle mouseStart
		if( mouseHandled ) { return; }

		// we may have missed mouseup (out of window)
		(this._mouseStarted && this._mouseUp(event));

		this._mouseDownEvent = event;

		var that = this,
			btnIsLeft = (event.which === 1),
			// event.target.nodeName works around a bug in IE 8 with
			// disabled inputs (#7620)
			elIsCancel = (typeof this.options.cancel === "string" && event.target.nodeName ? $(event.target).closest(this.options.cancel).length : false);
		if (!btnIsLeft || elIsCancel || !this._mouseCapture(event)) {
			return true;
		}

		this.mouseDelayMet = !this.options.delay;
		if (!this.mouseDelayMet) {
			this._mouseDelayTimer = setTimeout(function() {
				that.mouseDelayMet = true;
			}, this.options.delay);
		}

		if (this._mouseDistanceMet(event) && this._mouseDelayMet(event)) {
			this._mouseStarted = (this._mouseStart(event) !== false);
			if (!this._mouseStarted) {
				event.preventDefault();
				return true;
			}
		}

		// Click event may never have fired (Gecko & Opera)
		if (true === $.data(event.target, this.widgetName + ".preventClickEvent")) {
			$.removeData(event.target, this.widgetName + ".preventClickEvent");
		}

		// these delegates are required to keep context
		this._mouseMoveDelegate = function(event) {
			return that._mouseMove(event);
		};
		this._mouseUpDelegate = function(event) {
			return that._mouseUp(event);
		};
		$(document)
			.bind("mousemove."+this.widgetName, this._mouseMoveDelegate)
			.bind("mouseup."+this.widgetName, this._mouseUpDelegate);

		event.preventDefault();

		mouseHandled = true;
		return true;
	},

	_mouseMove: function(event) {
		// IE mouseup check - mouseup happened when mouse was out of window
		if ($.ui.ie && ( !document.documentMode || document.documentMode < 9 ) && !event.button) {
			return this._mouseUp(event);
		}

		if (this._mouseStarted) {
			this._mouseDrag(event);
			return event.preventDefault();
		}

		if (this._mouseDistanceMet(event) && this._mouseDelayMet(event)) {
			this._mouseStarted =
				(this._mouseStart(this._mouseDownEvent, event) !== false);
			(this._mouseStarted ? this._mouseDrag(event) : this._mouseUp(event));
		}

		return !this._mouseStarted;
	},

	_mouseUp: function(event) {
		$(document)
			.unbind("mousemove."+this.widgetName, this._mouseMoveDelegate)
			.unbind("mouseup."+this.widgetName, this._mouseUpDelegate);

		if (this._mouseStarted) {
			this._mouseStarted = false;

			if (event.target === this._mouseDownEvent.target) {
				$.data(event.target, this.widgetName + ".preventClickEvent", true);
			}

			this._mouseStop(event);
		}

		return false;
	},

	_mouseDistanceMet: function(event) {
		return (Math.max(
				Math.abs(this._mouseDownEvent.pageX - event.pageX),
				Math.abs(this._mouseDownEvent.pageY - event.pageY)
			) >= this.options.distance
		);
	},

	_mouseDelayMet: function(/* event */) {
		return this.mouseDelayMet;
	},

	// These are placeholder methods, to be overriden by extending plugin
	_mouseStart: function(/* event */) {},
	_mouseDrag: function(/* event */) {},
	_mouseStop: function(/* event */) {},
	_mouseCapture: function(/* event */) { return true; }
});

})(jQuery);

(function( $, undefined ) {

$.widget("ui.draggable", $.ui.mouse, {
	version: "1.10.3",
	widgetEventPrefix: "drag",
	options: {
		addClasses: true,
		appendTo: "parent",
		axis: false,
		connectToSortable: false,
		containment: false,
		cursor: "auto",
		cursorAt: false,
		grid: false,
		handle: false,
		helper: "original",
		iframeFix: false,
		opacity: false,
		refreshPositions: false,
		revert: false,
		revertDuration: 500,
		scope: "default",
		scroll: true,
		scrollSensitivity: 20,
		scrollSpeed: 20,
		snap: false,
		snapMode: "both",
		snapTolerance: 20,
		stack: false,
		zIndex: false,

		// callbacks
		drag: null,
		start: null,
		stop: null
	},
	_create: function() {

		if (this.options.helper === "original" && !(/^(?:r|a|f)/).test(this.element.css("position"))) {
			this.element[0].style.position = "relative";
		}
		if (this.options.addClasses){
			this.element.addClass("ui-draggable");
		}
		if (this.options.disabled){
			this.element.addClass("ui-draggable-disabled");
		}

		this._mouseInit();

	},

	_destroy: function() {
		this.element.removeClass( "ui-draggable ui-draggable-dragging ui-draggable-disabled" );
		this._mouseDestroy();
	},

	_mouseCapture: function(event) {

		var o = this.options;

		// among others, prevent a drag on a resizable-handle
		if (this.helper || o.disabled || $(event.target).closest(".ui-resizable-handle").length > 0) {
			return false;
		}

		//Quit if we're not on a valid handle
		this.handle = this._getHandle(event);
		if (!this.handle) {
			return false;
		}

		$(o.iframeFix === true ? "iframe" : o.iframeFix).each(function() {
			$("<div class='ui-draggable-iframeFix' style='background: #fff;'></div>")
			.css({
				width: this.offsetWidth+"px", height: this.offsetHeight+"px",
				position: "absolute", opacity: "0.001", zIndex: 1000
			})
			.css($(this).offset())
			.appendTo("body");
		});

		return true;

	},

	_mouseStart: function(event) {

		var o = this.options;

		//Create and append the visible helper
		this.helper = this._createHelper(event);

		this.helper.addClass("ui-draggable-dragging");

		//Cache the helper size
		this._cacheHelperProportions();

		//If ddmanager is used for droppables, set the global draggable
		if($.ui.ddmanager) {
			$.ui.ddmanager.current = this;
		}

		/*
		 * - Position generation -
		 * This block generates everything position related - it's the core of draggables.
		 */

		//Cache the margins of the original element
		this._cacheMargins();

		//Store the helper's css position
		this.cssPosition = this.helper.css( "position" );
		this.scrollParent = this.helper.scrollParent();
		this.offsetParent = this.helper.offsetParent();
		this.offsetParentCssPosition = this.offsetParent.css( "position" );

		//The element's absolute position on the page minus margins
		this.offset = this.positionAbs = this.element.offset();
		this.offset = {
			top: this.offset.top - this.margins.top,
			left: this.offset.left - this.margins.left
		};

		//Reset scroll cache
		this.offset.scroll = false;

		$.extend(this.offset, {
			click: { //Where the click happened, relative to the element
				left: event.pageX - this.offset.left,
				top: event.pageY - this.offset.top
			},
			parent: this._getParentOffset(),
			relative: this._getRelativeOffset() //This is a relative to absolute position minus the actual position calculation - only used for relative positioned helper
		});

		//Generate the original position
		this.originalPosition = this.position = this._generatePosition(event);
		this.originalPageX = event.pageX;
		this.originalPageY = event.pageY;

		//Adjust the mouse offset relative to the helper if "cursorAt" is supplied
		(o.cursorAt && this._adjustOffsetFromHelper(o.cursorAt));

		//Set a containment if given in the options
		this._setContainment();

		//Trigger event + callbacks
		if(this._trigger("start", event) === false) {
			this._clear();
			return false;
		}

		//Recache the helper size
		this._cacheHelperProportions();

		//Prepare the droppable offsets
		if ($.ui.ddmanager && !o.dropBehaviour) {
			$.ui.ddmanager.prepareOffsets(this, event);
		}


		this._mouseDrag(event, true); //Execute the drag once - this causes the helper not to be visible before getting its correct position

		//If the ddmanager is used for droppables, inform the manager that dragging has started (see #5003)
		if ( $.ui.ddmanager ) {
			$.ui.ddmanager.dragStart(this, event);
		}

		return true;
	},

	_mouseDrag: function(event, noPropagation) {
		// reset any necessary cached properties (see #5009)
		if ( this.offsetParentCssPosition === "fixed" ) {
			this.offset.parent = this._getParentOffset();
		}

		//Compute the helpers position
		this.position = this._generatePosition(event);
		this.positionAbs = this._convertPositionTo("absolute");

		//Call plugins and callbacks and use the resulting position if something is returned
		if (!noPropagation) {
			var ui = this._uiHash();
			if(this._trigger("drag", event, ui) === false) {
				this._mouseUp({});
				return false;
			}
			this.position = ui.position;
		}

		if(!this.options.axis || this.options.axis !== "y") {
			this.helper[0].style.left = this.position.left+"px";
		}
		if(!this.options.axis || this.options.axis !== "x") {
			this.helper[0].style.top = this.position.top+"px";
		}
		if($.ui.ddmanager) {
			$.ui.ddmanager.drag(this, event);
		}

		return false;
	},

	_mouseStop: function(event) {

		//If we are using droppables, inform the manager about the drop
		var that = this,
			dropped = false;
		if ($.ui.ddmanager && !this.options.dropBehaviour) {
			dropped = $.ui.ddmanager.drop(this, event);
		}

		//if a drop comes from outside (a sortable)
		if(this.dropped) {
			dropped = this.dropped;
			this.dropped = false;
		}

		//if the original element is no longer in the DOM don't bother to continue (see #8269)
		if ( this.options.helper === "original" && !$.contains( this.element[ 0 ].ownerDocument, this.element[ 0 ] ) ) {
			return false;
		}

		if((this.options.revert === "invalid" && !dropped) || (this.options.revert === "valid" && dropped) || this.options.revert === true || ($.isFunction(this.options.revert) && this.options.revert.call(this.element, dropped))) {
			$(this.helper).animate(this.originalPosition, parseInt(this.options.revertDuration, 10), function() {
				if(that._trigger("stop", event) !== false) {
					that._clear();
				}
			});
		} else {
			if(this._trigger("stop", event) !== false) {
				this._clear();
			}
		}

		return false;
	},

	_mouseUp: function(event) {
		//Remove frame helpers
		$("div.ui-draggable-iframeFix").each(function() {
			this.parentNode.removeChild(this);
		});

		//If the ddmanager is used for droppables, inform the manager that dragging has stopped (see #5003)
		if( $.ui.ddmanager ) {
			$.ui.ddmanager.dragStop(this, event);
		}

		return $.ui.mouse.prototype._mouseUp.call(this, event);
	},

	cancel: function() {

		if(this.helper.is(".ui-draggable-dragging")) {
			this._mouseUp({});
		} else {
			this._clear();
		}

		return this;

	},

	_getHandle: function(event) {
		return this.options.handle ?
			!!$( event.target ).closest( this.element.find( this.options.handle ) ).length :
			true;
	},

	_createHelper: function(event) {

		var o = this.options,
			helper = $.isFunction(o.helper) ? $(o.helper.apply(this.element[0], [event])) : (o.helper === "clone" ? this.element.clone().removeAttr("id") : this.element);

		if(!helper.parents("body").length) {
			helper.appendTo((o.appendTo === "parent" ? this.element[0].parentNode : o.appendTo));
		}

		if(helper[0] !== this.element[0] && !(/(fixed|absolute)/).test(helper.css("position"))) {
			helper.css("position", "absolute");
		}

		return helper;

	},

	_adjustOffsetFromHelper: function(obj) {
		if (typeof obj === "string") {
			obj = obj.split(" ");
		}
		if ($.isArray(obj)) {
			obj = {left: +obj[0], top: +obj[1] || 0};
		}
		if ("left" in obj) {
			this.offset.click.left = obj.left + this.margins.left;
		}
		if ("right" in obj) {
			this.offset.click.left = this.helperProportions.width - obj.right + this.margins.left;
		}
		if ("top" in obj) {
			this.offset.click.top = obj.top + this.margins.top;
		}
		if ("bottom" in obj) {
			this.offset.click.top = this.helperProportions.height - obj.bottom + this.margins.top;
		}
	},

	_getParentOffset: function() {

		//Get the offsetParent and cache its position
		var po = this.offsetParent.offset();

		// This is a special case where we need to modify a offset calculated on start, since the following happened:
		// 1. The position of the helper is absolute, so it's position is calculated based on the next positioned parent
		// 2. The actual offset parent is a child of the scroll parent, and the scroll parent isn't the document, which means that
		//    the scroll is included in the initial calculation of the offset of the parent, and never recalculated upon drag
		if(this.cssPosition === "absolute" && this.scrollParent[0] !== document && $.contains(this.scrollParent[0], this.offsetParent[0])) {
			po.left += this.scrollParent.scrollLeft();
			po.top += this.scrollParent.scrollTop();
		}

		//This needs to be actually done for all browsers, since pageX/pageY includes this information
		//Ugly IE fix
		if((this.offsetParent[0] === document.body) ||
			(this.offsetParent[0].tagName && this.offsetParent[0].tagName.toLowerCase() === "html" && $.ui.ie)) {
			po = { top: 0, left: 0 };
		}

		return {
			top: po.top + (parseInt(this.offsetParent.css("borderTopWidth"),10) || 0),
			left: po.left + (parseInt(this.offsetParent.css("borderLeftWidth"),10) || 0)
		};

	},

	_getRelativeOffset: function() {

		if(this.cssPosition === "relative") {
			var p = this.element.position();
			return {
				top: p.top - (parseInt(this.helper.css("top"),10) || 0) + this.scrollParent.scrollTop(),
				left: p.left - (parseInt(this.helper.css("left"),10) || 0) + this.scrollParent.scrollLeft()
			};
		} else {
			return { top: 0, left: 0 };
		}

	},

	_cacheMargins: function() {
		this.margins = {
			left: (parseInt(this.element.css("marginLeft"),10) || 0),
			top: (parseInt(this.element.css("marginTop"),10) || 0),
			right: (parseInt(this.element.css("marginRight"),10) || 0),
			bottom: (parseInt(this.element.css("marginBottom"),10) || 0)
		};
	},

	_cacheHelperProportions: function() {
		this.helperProportions = {
			width: this.helper.outerWidth(),
			height: this.helper.outerHeight()
		};
	},

	_setContainment: function() {

		var over, c, ce,
			o = this.options;

		if ( !o.containment ) {
			this.containment = null;
			return;
		}

		if ( o.containment === "window" ) {
			this.containment = [
				$( window ).scrollLeft() - this.offset.relative.left - this.offset.parent.left,
				$( window ).scrollTop() - this.offset.relative.top - this.offset.parent.top,
				$( window ).scrollLeft() + $( window ).width() - this.helperProportions.width - this.margins.left,
				$( window ).scrollTop() + ( $( window ).height() || document.body.parentNode.scrollHeight ) - this.helperProportions.height - this.margins.top
			];
			return;
		}

		if ( o.containment === "document") {
			this.containment = [
				0,
				0,
				$( document ).width() - this.helperProportions.width - this.margins.left,
				( $( document ).height() || document.body.parentNode.scrollHeight ) - this.helperProportions.height - this.margins.top
			];
			return;
		}

		if ( o.containment.constructor === Array ) {
			this.containment = o.containment;
			return;
		}

		if ( o.containment === "parent" ) {
			o.containment = this.helper[ 0 ].parentNode;
		}

		c = $( o.containment );
		ce = c[ 0 ];

		if( !ce ) {
			return;
		}

		over = c.css( "overflow" ) !== "hidden";

		this.containment = [
			( parseInt( c.css( "borderLeftWidth" ), 10 ) || 0 ) + ( parseInt( c.css( "paddingLeft" ), 10 ) || 0 ),
			( parseInt( c.css( "borderTopWidth" ), 10 ) || 0 ) + ( parseInt( c.css( "paddingTop" ), 10 ) || 0 ) ,
			( over ? Math.max( ce.scrollWidth, ce.offsetWidth ) : ce.offsetWidth ) - ( parseInt( c.css( "borderRightWidth" ), 10 ) || 0 ) - ( parseInt( c.css( "paddingRight" ), 10 ) || 0 ) - this.helperProportions.width - this.margins.left - this.margins.right,
			( over ? Math.max( ce.scrollHeight, ce.offsetHeight ) : ce.offsetHeight ) - ( parseInt( c.css( "borderBottomWidth" ), 10 ) || 0 ) - ( parseInt( c.css( "paddingBottom" ), 10 ) || 0 ) - this.helperProportions.height - this.margins.top  - this.margins.bottom
		];
		this.relative_container = c;
	},

	_convertPositionTo: function(d, pos) {

		if(!pos) {
			pos = this.position;
		}

		var mod = d === "absolute" ? 1 : -1,
			scroll = this.cssPosition === "absolute" && !( this.scrollParent[ 0 ] !== document && $.contains( this.scrollParent[ 0 ], this.offsetParent[ 0 ] ) ) ? this.offsetParent : this.scrollParent;

		//Cache the scroll
		if (!this.offset.scroll) {
			this.offset.scroll = {top : scroll.scrollTop(), left : scroll.scrollLeft()};
		}

		return {
			top: (
				pos.top	+																// The absolute mouse position
				this.offset.relative.top * mod +										// Only for relative positioned nodes: Relative offset from element to offset parent
				this.offset.parent.top * mod -										// The offsetParent's offset without borders (offset + border)
				( ( this.cssPosition === "fixed" ? -this.scrollParent.scrollTop() : this.offset.scroll.top ) * mod )
			),
			left: (
				pos.left +																// The absolute mouse position
				this.offset.relative.left * mod +										// Only for relative positioned nodes: Relative offset from element to offset parent
				this.offset.parent.left * mod	-										// The offsetParent's offset without borders (offset + border)
				( ( this.cssPosition === "fixed" ? -this.scrollParent.scrollLeft() : this.offset.scroll.left ) * mod )
			)
		};

	},

	_generatePosition: function(event) {

		var containment, co, top, left,
			o = this.options,
			scroll = this.cssPosition === "absolute" && !( this.scrollParent[ 0 ] !== document && $.contains( this.scrollParent[ 0 ], this.offsetParent[ 0 ] ) ) ? this.offsetParent : this.scrollParent,
			pageX = event.pageX,
			pageY = event.pageY;

		//Cache the scroll
		if (!this.offset.scroll) {
			this.offset.scroll = {top : scroll.scrollTop(), left : scroll.scrollLeft()};
		}

		/*
		 * - Position constraining -
		 * Constrain the position to a mix of grid, containment.
		 */

		// If we are not dragging yet, we won't check for options
		if ( this.originalPosition ) {
			if ( this.containment ) {
				if ( this.relative_container ){
					co = this.relative_container.offset();
					containment = [
						this.containment[ 0 ] + co.left,
						this.containment[ 1 ] + co.top,
						this.containment[ 2 ] + co.left,
						this.containment[ 3 ] + co.top
					];
				}
				else {
					containment = this.containment;
				}

				if(event.pageX - this.offset.click.left < containment[0]) {
					pageX = containment[0] + this.offset.click.left;
				}
				if(event.pageY - this.offset.click.top < containment[1]) {
					pageY = containment[1] + this.offset.click.top;
				}
				if(event.pageX - this.offset.click.left > containment[2]) {
					pageX = containment[2] + this.offset.click.left;
				}
				if(event.pageY - this.offset.click.top > containment[3]) {
					pageY = containment[3] + this.offset.click.top;
				}
			}

			if(o.grid) {
				//Check for grid elements set to 0 to prevent divide by 0 error causing invalid argument errors in IE (see ticket #6950)
				top = o.grid[1] ? this.originalPageY + Math.round((pageY - this.originalPageY) / o.grid[1]) * o.grid[1] : this.originalPageY;
				pageY = containment ? ((top - this.offset.click.top >= containment[1] || top - this.offset.click.top > containment[3]) ? top : ((top - this.offset.click.top >= containment[1]) ? top - o.grid[1] : top + o.grid[1])) : top;

				left = o.grid[0] ? this.originalPageX + Math.round((pageX - this.originalPageX) / o.grid[0]) * o.grid[0] : this.originalPageX;
				pageX = containment ? ((left - this.offset.click.left >= containment[0] || left - this.offset.click.left > containment[2]) ? left : ((left - this.offset.click.left >= containment[0]) ? left - o.grid[0] : left + o.grid[0])) : left;
			}

		}

		return {
			top: (
				pageY -																	// The absolute mouse position
				this.offset.click.top	-												// Click offset (relative to the element)
				this.offset.relative.top -												// Only for relative positioned nodes: Relative offset from element to offset parent
				this.offset.parent.top +												// The offsetParent's offset without borders (offset + border)
				( this.cssPosition === "fixed" ? -this.scrollParent.scrollTop() : this.offset.scroll.top )
			),
			left: (
				pageX -																	// The absolute mouse position
				this.offset.click.left -												// Click offset (relative to the element)
				this.offset.relative.left -												// Only for relative positioned nodes: Relative offset from element to offset parent
				this.offset.parent.left +												// The offsetParent's offset without borders (offset + border)
				( this.cssPosition === "fixed" ? -this.scrollParent.scrollLeft() : this.offset.scroll.left )
			)
		};

	},

	_clear: function() {
		this.helper.removeClass("ui-draggable-dragging");
		if(this.helper[0] !== this.element[0] && !this.cancelHelperRemoval) {
			this.helper.remove();
		}
		this.helper = null;
		this.cancelHelperRemoval = false;
	},

	// From now on bulk stuff - mainly helpers

	_trigger: function(type, event, ui) {
		ui = ui || this._uiHash();
		$.ui.plugin.call(this, type, [event, ui]);
		//The absolute position has to be recalculated after plugins
		if(type === "drag") {
			this.positionAbs = this._convertPositionTo("absolute");
		}
		return $.Widget.prototype._trigger.call(this, type, event, ui);
	},

	plugins: {},

	_uiHash: function() {
		return {
			helper: this.helper,
			position: this.position,
			originalPosition: this.originalPosition,
			offset: this.positionAbs
		};
	}

});

$.ui.plugin.add("draggable", "connectToSortable", {
	start: function(event, ui) {

		var inst = $(this).data("ui-draggable"), o = inst.options,
			uiSortable = $.extend({}, ui, { item: inst.element });
		inst.sortables = [];
		$(o.connectToSortable).each(function() {
			var sortable = $.data(this, "ui-sortable");
			if (sortable && !sortable.options.disabled) {
				inst.sortables.push({
					instance: sortable,
					shouldRevert: sortable.options.revert
				});
				sortable.refreshPositions();	// Call the sortable's refreshPositions at drag start to refresh the containerCache since the sortable container cache is used in drag and needs to be up to date (this will ensure it's initialised as well as being kept in step with any changes that might have happened on the page).
				sortable._trigger("activate", event, uiSortable);
			}
		});

	},
	stop: function(event, ui) {

		//If we are still over the sortable, we fake the stop event of the sortable, but also remove helper
		var inst = $(this).data("ui-draggable"),
			uiSortable = $.extend({}, ui, { item: inst.element });

		$.each(inst.sortables, function() {
			if(this.instance.isOver) {

				this.instance.isOver = 0;

				inst.cancelHelperRemoval = true; //Don't remove the helper in the draggable instance
				this.instance.cancelHelperRemoval = false; //Remove it in the sortable instance (so sortable plugins like revert still work)

				//The sortable revert is supported, and we have to set a temporary dropped variable on the draggable to support revert: "valid/invalid"
				if(this.shouldRevert) {
					this.instance.options.revert = this.shouldRevert;
				}

				//Trigger the stop of the sortable
				this.instance._mouseStop(event);

				this.instance.options.helper = this.instance.options._helper;

				//If the helper has been the original item, restore properties in the sortable
				if(inst.options.helper === "original") {
					this.instance.currentItem.css({ top: "auto", left: "auto" });
				}

			} else {
				this.instance.cancelHelperRemoval = false; //Remove the helper in the sortable instance
				this.instance._trigger("deactivate", event, uiSortable);
			}

		});

	},
	drag: function(event, ui) {

		var inst = $(this).data("ui-draggable"), that = this;

		$.each(inst.sortables, function() {

			var innermostIntersecting = false,
				thisSortable = this;

			//Copy over some variables to allow calling the sortable's native _intersectsWith
			this.instance.positionAbs = inst.positionAbs;
			this.instance.helperProportions = inst.helperProportions;
			this.instance.offset.click = inst.offset.click;

			if(this.instance._intersectsWith(this.instance.containerCache)) {
				innermostIntersecting = true;
				$.each(inst.sortables, function () {
					this.instance.positionAbs = inst.positionAbs;
					this.instance.helperProportions = inst.helperProportions;
					this.instance.offset.click = inst.offset.click;
					if (this !== thisSortable &&
						this.instance._intersectsWith(this.instance.containerCache) &&
						$.contains(thisSortable.instance.element[0], this.instance.element[0])
					) {
						innermostIntersecting = false;
					}
					return innermostIntersecting;
				});
			}


			if(innermostIntersecting) {
				//If it intersects, we use a little isOver variable and set it once, so our move-in stuff gets fired only once
				if(!this.instance.isOver) {

					this.instance.isOver = 1;
					//Now we fake the start of dragging for the sortable instance,
					//by cloning the list group item, appending it to the sortable and using it as inst.currentItem
					//We can then fire the start event of the sortable with our passed browser event, and our own helper (so it doesn't create a new one)
					this.instance.currentItem = $(that).clone().removeAttr("id").appendTo(this.instance.element).data("ui-sortable-item", true);
					this.instance.options._helper = this.instance.options.helper; //Store helper option to later restore it
					this.instance.options.helper = function() { return ui.helper[0]; };

					event.target = this.instance.currentItem[0];
					this.instance._mouseCapture(event, true);
					this.instance._mouseStart(event, true, true);

					//Because the browser event is way off the new appended portlet, we modify a couple of variables to reflect the changes
					this.instance.offset.click.top = inst.offset.click.top;
					this.instance.offset.click.left = inst.offset.click.left;
					this.instance.offset.parent.left -= inst.offset.parent.left - this.instance.offset.parent.left;
					this.instance.offset.parent.top -= inst.offset.parent.top - this.instance.offset.parent.top;

					inst._trigger("toSortable", event);
					inst.dropped = this.instance.element; //draggable revert needs that
					//hack so receive/update callbacks work (mostly)
					inst.currentItem = inst.element;
					this.instance.fromOutside = inst;

				}

				//Provided we did all the previous steps, we can fire the drag event of the sortable on every draggable drag, when it intersects with the sortable
				if(this.instance.currentItem) {
					this.instance._mouseDrag(event);
				}

			} else {

				//If it doesn't intersect with the sortable, and it intersected before,
				//we fake the drag stop of the sortable, but make sure it doesn't remove the helper by using cancelHelperRemoval
				if(this.instance.isOver) {

					this.instance.isOver = 0;
					this.instance.cancelHelperRemoval = true;

					//Prevent reverting on this forced stop
					this.instance.options.revert = false;

					// The out event needs to be triggered independently
					this.instance._trigger("out", event, this.instance._uiHash(this.instance));

					this.instance._mouseStop(event, true);
					this.instance.options.helper = this.instance.options._helper;

					//Now we remove our currentItem, the list group clone again, and the placeholder, and animate the helper back to it's original size
					this.instance.currentItem.remove();
					if(this.instance.placeholder) {
						this.instance.placeholder.remove();
					}

					inst._trigger("fromSortable", event);
					inst.dropped = false; //draggable revert needs that
				}

			}

		});

	}
});

$.ui.plugin.add("draggable", "cursor", {
	start: function() {
		var t = $("body"), o = $(this).data("ui-draggable").options;
		if (t.css("cursor")) {
			o._cursor = t.css("cursor");
		}
		t.css("cursor", o.cursor);
	},
	stop: function() {
		var o = $(this).data("ui-draggable").options;
		if (o._cursor) {
			$("body").css("cursor", o._cursor);
		}
	}
});

$.ui.plugin.add("draggable", "opacity", {
	start: function(event, ui) {
		var t = $(ui.helper), o = $(this).data("ui-draggable").options;
		if(t.css("opacity")) {
			o._opacity = t.css("opacity");
		}
		t.css("opacity", o.opacity);
	},
	stop: function(event, ui) {
		var o = $(this).data("ui-draggable").options;
		if(o._opacity) {
			$(ui.helper).css("opacity", o._opacity);
		}
	}
});

$.ui.plugin.add("draggable", "scroll", {
	start: function() {
		var i = $(this).data("ui-draggable");
		if(i.scrollParent[0] !== document && i.scrollParent[0].tagName !== "HTML") {
			i.overflowOffset = i.scrollParent.offset();
		}
	},
	drag: function( event ) {

		var i = $(this).data("ui-draggable"), o = i.options, scrolled = false;

		if(i.scrollParent[0] !== document && i.scrollParent[0].tagName !== "HTML") {

			if(!o.axis || o.axis !== "x") {
				if((i.overflowOffset.top + i.scrollParent[0].offsetHeight) - event.pageY < o.scrollSensitivity) {
					i.scrollParent[0].scrollTop = scrolled = i.scrollParent[0].scrollTop + o.scrollSpeed;
				} else if(event.pageY - i.overflowOffset.top < o.scrollSensitivity) {
					i.scrollParent[0].scrollTop = scrolled = i.scrollParent[0].scrollTop - o.scrollSpeed;
				}
			}

			if(!o.axis || o.axis !== "y") {
				if((i.overflowOffset.left + i.scrollParent[0].offsetWidth) - event.pageX < o.scrollSensitivity) {
					i.scrollParent[0].scrollLeft = scrolled = i.scrollParent[0].scrollLeft + o.scrollSpeed;
				} else if(event.pageX - i.overflowOffset.left < o.scrollSensitivity) {
					i.scrollParent[0].scrollLeft = scrolled = i.scrollParent[0].scrollLeft - o.scrollSpeed;
				}
			}

		} else {

			if(!o.axis || o.axis !== "x") {
				if(event.pageY - $(document).scrollTop() < o.scrollSensitivity) {
					scrolled = $(document).scrollTop($(document).scrollTop() - o.scrollSpeed);
				} else if($(window).height() - (event.pageY - $(document).scrollTop()) < o.scrollSensitivity) {
					scrolled = $(document).scrollTop($(document).scrollTop() + o.scrollSpeed);
				}
			}

			if(!o.axis || o.axis !== "y") {
				if(event.pageX - $(document).scrollLeft() < o.scrollSensitivity) {
					scrolled = $(document).scrollLeft($(document).scrollLeft() - o.scrollSpeed);
				} else if($(window).width() - (event.pageX - $(document).scrollLeft()) < o.scrollSensitivity) {
					scrolled = $(document).scrollLeft($(document).scrollLeft() + o.scrollSpeed);
				}
			}

		}

		if(scrolled !== false && $.ui.ddmanager && !o.dropBehaviour) {
			$.ui.ddmanager.prepareOffsets(i, event);
		}

	}
});

$.ui.plugin.add("draggable", "snap", {
	start: function() {

		var i = $(this).data("ui-draggable"),
			o = i.options;

		i.snapElements = [];

		$(o.snap.constructor !== String ? ( o.snap.items || ":data(ui-draggable)" ) : o.snap).each(function() {
			var $t = $(this),
				$o = $t.offset();
			if(this !== i.element[0]) {
				i.snapElements.push({
					item: this,
					width: $t.outerWidth(), height: $t.outerHeight(),
					top: $o.top, left: $o.left
				});
			}
		});

	},
	drag: function(event, ui) {

		var ts, bs, ls, rs, l, r, t, b, i, first,
			inst = $(this).data("ui-draggable"),
			o = inst.options,
			d = o.snapTolerance,
			x1 = ui.offset.left, x2 = x1 + inst.helperProportions.width,
			y1 = ui.offset.top, y2 = y1 + inst.helperProportions.height;

		for (i = inst.snapElements.length - 1; i >= 0; i--){

			l = inst.snapElements[i].left;
			r = l + inst.snapElements[i].width;
			t = inst.snapElements[i].top;
			b = t + inst.snapElements[i].height;

			if ( x2 < l - d || x1 > r + d || y2 < t - d || y1 > b + d || !$.contains( inst.snapElements[ i ].item.ownerDocument, inst.snapElements[ i ].item ) ) {
				if(inst.snapElements[i].snapping) {
					(inst.options.snap.release && inst.options.snap.release.call(inst.element, event, $.extend(inst._uiHash(), { snapItem: inst.snapElements[i].item })));
				}
				inst.snapElements[i].snapping = false;
				continue;
			}

			if(o.snapMode !== "inner") {
				ts = Math.abs(t - y2) <= d;
				bs = Math.abs(b - y1) <= d;
				ls = Math.abs(l - x2) <= d;
				rs = Math.abs(r - x1) <= d;
				if(ts) {
					ui.position.top = inst._convertPositionTo("relative", { top: t - inst.helperProportions.height, left: 0 }).top - inst.margins.top;
				}
				if(bs) {
					ui.position.top = inst._convertPositionTo("relative", { top: b, left: 0 }).top - inst.margins.top;
				}
				if(ls) {
					ui.position.left = inst._convertPositionTo("relative", { top: 0, left: l - inst.helperProportions.width }).left - inst.margins.left;
				}
				if(rs) {
					ui.position.left = inst._convertPositionTo("relative", { top: 0, left: r }).left - inst.margins.left;
				}
			}

			first = (ts || bs || ls || rs);

			if(o.snapMode !== "outer") {
				ts = Math.abs(t - y1) <= d;
				bs = Math.abs(b - y2) <= d;
				ls = Math.abs(l - x1) <= d;
				rs = Math.abs(r - x2) <= d;
				if(ts) {
					ui.position.top = inst._convertPositionTo("relative", { top: t, left: 0 }).top - inst.margins.top;
				}
				if(bs) {
					ui.position.top = inst._convertPositionTo("relative", { top: b - inst.helperProportions.height, left: 0 }).top - inst.margins.top;
				}
				if(ls) {
					ui.position.left = inst._convertPositionTo("relative", { top: 0, left: l }).left - inst.margins.left;
				}
				if(rs) {
					ui.position.left = inst._convertPositionTo("relative", { top: 0, left: r - inst.helperProportions.width }).left - inst.margins.left;
				}
			}

			if(!inst.snapElements[i].snapping && (ts || bs || ls || rs || first)) {
				(inst.options.snap.snap && inst.options.snap.snap.call(inst.element, event, $.extend(inst._uiHash(), { snapItem: inst.snapElements[i].item })));
			}
			inst.snapElements[i].snapping = (ts || bs || ls || rs || first);

		}

	}
});

$.ui.plugin.add("draggable", "stack", {
	start: function() {
		var min,
			o = this.data("ui-draggable").options,
			group = $.makeArray($(o.stack)).sort(function(a,b) {
				return (parseInt($(a).css("zIndex"),10) || 0) - (parseInt($(b).css("zIndex"),10) || 0);
			});

		if (!group.length) { return; }

		min = parseInt($(group[0]).css("zIndex"), 10) || 0;
		$(group).each(function(i) {
			$(this).css("zIndex", min + i);
		});
		this.css("zIndex", (min + group.length));
	}
});

$.ui.plugin.add("draggable", "zIndex", {
	start: function(event, ui) {
		var t = $(ui.helper), o = $(this).data("ui-draggable").options;
		if(t.css("zIndex")) {
			o._zIndex = t.css("zIndex");
		}
		t.css("zIndex", o.zIndex);
	},
	stop: function(event, ui) {
		var o = $(this).data("ui-draggable").options;
		if(o._zIndex) {
			$(ui.helper).css("zIndex", o._zIndex);
		}
	}
});

})(jQuery);

(function( $, undefined ) {

function isOverAxis( x, reference, size ) {
	return ( x > reference ) && ( x < ( reference + size ) );
}

$.widget("ui.droppable", {
	version: "1.10.3",
	widgetEventPrefix: "drop",
	options: {
		accept: "*",
		activeClass: false,
		addClasses: true,
		greedy: false,
		hoverClass: false,
		scope: "default",
		tolerance: "intersect",

		// callbacks
		activate: null,
		deactivate: null,
		drop: null,
		out: null,
		over: null
	},
	_create: function() {

		var o = this.options,
			accept = o.accept;

		this.isover = false;
		this.isout = true;

		this.accept = $.isFunction(accept) ? accept : function(d) {
			return d.is(accept);
		};

		//Store the droppable's proportions
		this.proportions = { width: this.element[0].offsetWidth, height: this.element[0].offsetHeight };

		// Add the reference and positions to the manager
		$.ui.ddmanager.droppables[o.scope] = $.ui.ddmanager.droppables[o.scope] || [];
		$.ui.ddmanager.droppables[o.scope].push(this);

		(o.addClasses && this.element.addClass("ui-droppable"));

	},

	_destroy: function() {
		var i = 0,
			drop = $.ui.ddmanager.droppables[this.options.scope];

		for ( ; i < drop.length; i++ ) {
			if ( drop[i] === this ) {
				drop.splice(i, 1);
			}
		}

		this.element.removeClass("ui-droppable ui-droppable-disabled");
	},

	_setOption: function(key, value) {

		if(key === "accept") {
			this.accept = $.isFunction(value) ? value : function(d) {
				return d.is(value);
			};
		}
		$.Widget.prototype._setOption.apply(this, arguments);
	},

	_activate: function(event) {
		var draggable = $.ui.ddmanager.current;
		if(this.options.activeClass) {
			this.element.addClass(this.options.activeClass);
		}
		if(draggable){
			this._trigger("activate", event, this.ui(draggable));
		}
	},

	_deactivate: function(event) {
		var draggable = $.ui.ddmanager.current;
		if(this.options.activeClass) {
			this.element.removeClass(this.options.activeClass);
		}
		if(draggable){
			this._trigger("deactivate", event, this.ui(draggable));
		}
	},

	_over: function(event) {

		var draggable = $.ui.ddmanager.current;

		// Bail if draggable and droppable are same element
		if (!draggable || (draggable.currentItem || draggable.element)[0] === this.element[0]) {
			return;
		}

		if (this.accept.call(this.element[0],(draggable.currentItem || draggable.element))) {
			if(this.options.hoverClass) {
				this.element.addClass(this.options.hoverClass);
			}
			this._trigger("over", event, this.ui(draggable));
		}

	},

	_out: function(event) {

		var draggable = $.ui.ddmanager.current;

		// Bail if draggable and droppable are same element
		if (!draggable || (draggable.currentItem || draggable.element)[0] === this.element[0]) {
			return;
		}

		if (this.accept.call(this.element[0],(draggable.currentItem || draggable.element))) {
			if(this.options.hoverClass) {
				this.element.removeClass(this.options.hoverClass);
			}
			this._trigger("out", event, this.ui(draggable));
		}

	},

	_drop: function(event,custom) {

		var draggable = custom || $.ui.ddmanager.current,
			childrenIntersection = false;

		// Bail if draggable and droppable are same element
		if (!draggable || (draggable.currentItem || draggable.element)[0] === this.element[0]) {
			return false;
		}

		this.element.find(":data(ui-droppable)").not(".ui-draggable-dragging").each(function() {
			var inst = $.data(this, "ui-droppable");
			if(
				inst.options.greedy &&
				!inst.options.disabled &&
				inst.options.scope === draggable.options.scope &&
				inst.accept.call(inst.element[0], (draggable.currentItem || draggable.element)) &&
				$.ui.intersect(draggable, $.extend(inst, { offset: inst.element.offset() }), inst.options.tolerance)
			) { childrenIntersection = true; return false; }
		});
		if(childrenIntersection) {
			return false;
		}

		if(this.accept.call(this.element[0],(draggable.currentItem || draggable.element))) {
			if(this.options.activeClass) {
				this.element.removeClass(this.options.activeClass);
			}
			if(this.options.hoverClass) {
				this.element.removeClass(this.options.hoverClass);
			}
			this._trigger("drop", event, this.ui(draggable));
			return this.element;
		}

		return false;

	},

	ui: function(c) {
		return {
			draggable: (c.currentItem || c.element),
			helper: c.helper,
			position: c.position,
			offset: c.positionAbs
		};
	}

});

$.ui.intersect = function(draggable, droppable, toleranceMode) {

	if (!droppable.offset) {
		return false;
	}

	var draggableLeft, draggableTop,
		x1 = (draggable.positionAbs || draggable.position.absolute).left, x2 = x1 + draggable.helperProportions.width,
		y1 = (draggable.positionAbs || draggable.position.absolute).top, y2 = y1 + draggable.helperProportions.height,
		l = droppable.offset.left, r = l + droppable.proportions.width,
		t = droppable.offset.top, b = t + droppable.proportions.height;

	switch (toleranceMode) {
		case "fit":
			return (l <= x1 && x2 <= r && t <= y1 && y2 <= b);
		case "intersect":
			return (l < x1 + (draggable.helperProportions.width / 2) && // Right Half
				x2 - (draggable.helperProportions.width / 2) < r && // Left Half
				t < y1 + (draggable.helperProportions.height / 2) && // Bottom Half
				y2 - (draggable.helperProportions.height / 2) < b ); // Top Half
		case "pointer":
			draggableLeft = ((draggable.positionAbs || draggable.position.absolute).left + (draggable.clickOffset || draggable.offset.click).left);
			draggableTop = ((draggable.positionAbs || draggable.position.absolute).top + (draggable.clickOffset || draggable.offset.click).top);
			return isOverAxis( draggableTop, t, droppable.proportions.height ) && isOverAxis( draggableLeft, l, droppable.proportions.width );
		case "touch":
			return (
				(y1 >= t && y1 <= b) ||	// Top edge touching
				(y2 >= t && y2 <= b) ||	// Bottom edge touching
				(y1 < t && y2 > b)		// Surrounded vertically
			) && (
				(x1 >= l && x1 <= r) ||	// Left edge touching
				(x2 >= l && x2 <= r) ||	// Right edge touching
				(x1 < l && x2 > r)		// Surrounded horizontally
			);
		default:
			return false;
		}

};

/*
	This manager tracks offsets of draggables and droppables
*/
$.ui.ddmanager = {
	current: null,
	droppables: { "default": [] },
	prepareOffsets: function(t, event) {

		var i, j,
			m = $.ui.ddmanager.droppables[t.options.scope] || [],
			type = event ? event.type : null, // workaround for #2317
			list = (t.currentItem || t.element).find(":data(ui-droppable)").addBack();

		droppablesLoop: for (i = 0; i < m.length; i++) {

			//No disabled and non-accepted
			if(m[i].options.disabled || (t && !m[i].accept.call(m[i].element[0],(t.currentItem || t.element)))) {
				continue;
			}

			// Filter out elements in the current dragged item
			for (j=0; j < list.length; j++) {
				if(list[j] === m[i].element[0]) {
					m[i].proportions.height = 0;
					continue droppablesLoop;
				}
			}

			m[i].visible = m[i].element.css("display") !== "none";
			if(!m[i].visible) {
				continue;
			}

			//Activate the droppable if used directly from draggables
			if(type === "mousedown") {
				m[i]._activate.call(m[i], event);
			}

			m[i].offset = m[i].element.offset();
			m[i].proportions = { width: m[i].element[0].offsetWidth, height: m[i].element[0].offsetHeight };

		}

	},
	drop: function(draggable, event) {

		var dropped = false;
		// Create a copy of the droppables in case the list changes during the drop (#9116)
		$.each(($.ui.ddmanager.droppables[draggable.options.scope] || []).slice(), function() {

			if(!this.options) {
				return;
			}
			if (!this.options.disabled && this.visible && $.ui.intersect(draggable, this, this.options.tolerance)) {
				dropped = this._drop.call(this, event) || dropped;
			}

			if (!this.options.disabled && this.visible && this.accept.call(this.element[0],(draggable.currentItem || draggable.element))) {
				this.isout = true;
				this.isover = false;
				this._deactivate.call(this, event);
			}

		});
		return dropped;

	},
	dragStart: function( draggable, event ) {
		//Listen for scrolling so that if the dragging causes scrolling the position of the droppables can be recalculated (see #5003)
		draggable.element.parentsUntil( "body" ).bind( "scroll.droppable", function() {
			if( !draggable.options.refreshPositions ) {
				$.ui.ddmanager.prepareOffsets( draggable, event );
			}
		});
	},
	drag: function(draggable, event) {

		//If you have a highly dynamic page, you might try this option. It renders positions every time you move the mouse.
		if(draggable.options.refreshPositions) {
			$.ui.ddmanager.prepareOffsets(draggable, event);
		}

		//Run through all droppables and check their positions based on specific tolerance options
		$.each($.ui.ddmanager.droppables[draggable.options.scope] || [], function() {

			if(this.options.disabled || this.greedyChild || !this.visible) {
				return;
			}

			var parentInstance, scope, parent,
				intersects = $.ui.intersect(draggable, this, this.options.tolerance),
				c = !intersects && this.isover ? "isout" : (intersects && !this.isover ? "isover" : null);
			if(!c) {
				return;
			}

			if (this.options.greedy) {
				// find droppable parents with same scope
				scope = this.options.scope;
				parent = this.element.parents(":data(ui-droppable)").filter(function () {
					return $.data(this, "ui-droppable").options.scope === scope;
				});

				if (parent.length) {
					parentInstance = $.data(parent[0], "ui-droppable");
					parentInstance.greedyChild = (c === "isover");
				}
			}

			// we just moved into a greedy child
			if (parentInstance && c === "isover") {
				parentInstance.isover = false;
				parentInstance.isout = true;
				parentInstance._out.call(parentInstance, event);
			}

			this[c] = true;
			this[c === "isout" ? "isover" : "isout"] = false;
			this[c === "isover" ? "_over" : "_out"].call(this, event);

			// we just moved out of a greedy child
			if (parentInstance && c === "isout") {
				parentInstance.isout = false;
				parentInstance.isover = true;
				parentInstance._over.call(parentInstance, event);
			}
		});

	},
	dragStop: function( draggable, event ) {
		draggable.element.parentsUntil( "body" ).unbind( "scroll.droppable" );
		//Call prepareOffsets one final time since IE does not fire return scroll events when overflow was caused by drag (see #5003)
		if( !draggable.options.refreshPositions ) {
			$.ui.ddmanager.prepareOffsets( draggable, event );
		}
	}
};

})(jQuery);

(function( $, undefined ) {

function num(v) {
	return parseInt(v, 10) || 0;
}

function isNumber(value) {
	return !isNaN(parseInt(value, 10));
}

$.widget("ui.resizable", $.ui.mouse, {
	version: "1.10.3",
	widgetEventPrefix: "resize",
	options: {
		alsoResize: false,
		animate: false,
		animateDuration: "slow",
		animateEasing: "swing",
		aspectRatio: false,
		autoHide: false,
		containment: false,
		ghost: false,
		grid: false,
		handles: "e,s,se",
		helper: false,
		maxHeight: null,
		maxWidth: null,
		minHeight: 10,
		minWidth: 10,
		// See #7960
		zIndex: 90,

		// callbacks
		resize: null,
		start: null,
		stop: null
	},
	_create: function() {

		var n, i, handle, axis, hname,
			that = this,
			o = this.options;
		this.element.addClass("ui-resizable");

		$.extend(this, {
			_aspectRatio: !!(o.aspectRatio),
			aspectRatio: o.aspectRatio,
			originalElement: this.element,
			_proportionallyResizeElements: [],
			_helper: o.helper || o.ghost || o.animate ? o.helper || "ui-resizable-helper" : null
		});

		//Wrap the element if it cannot hold child nodes
		if(this.element[0].nodeName.match(/canvas|textarea|input|select|button|img/i)) {

			//Create a wrapper element and set the wrapper to the new current internal element
			this.element.wrap(
				$("<div class='ui-wrapper' style='overflow: hidden;'></div>").css({
					position: this.element.css("position"),
					width: this.element.outerWidth(),
					height: this.element.outerHeight(),
					top: this.element.css("top"),
					left: this.element.css("left")
				})
			);

			//Overwrite the original this.element
			this.element = this.element.parent().data(
				"ui-resizable", this.element.data("ui-resizable")
			);

			this.elementIsWrapper = true;

			//Move margins to the wrapper
			this.element.css({ marginLeft: this.originalElement.css("marginLeft"), marginTop: this.originalElement.css("marginTop"), marginRight: this.originalElement.css("marginRight"), marginBottom: this.originalElement.css("marginBottom") });
			this.originalElement.css({ marginLeft: 0, marginTop: 0, marginRight: 0, marginBottom: 0});

			//Prevent Safari textarea resize
			this.originalResizeStyle = this.originalElement.css("resize");
			this.originalElement.css("resize", "none");

			//Push the actual element to our proportionallyResize internal array
			this._proportionallyResizeElements.push(this.originalElement.css({ position: "static", zoom: 1, display: "block" }));

			// avoid IE jump (hard set the margin)
			this.originalElement.css({ margin: this.originalElement.css("margin") });

			// fix handlers offset
			this._proportionallyResize();

		}

		this.handles = o.handles || (!$(".ui-resizable-handle", this.element).length ? "e,s,se" : { n: ".ui-resizable-n", e: ".ui-resizable-e", s: ".ui-resizable-s", w: ".ui-resizable-w", se: ".ui-resizable-se", sw: ".ui-resizable-sw", ne: ".ui-resizable-ne", nw: ".ui-resizable-nw" });
		if(this.handles.constructor === String) {

			if ( this.handles === "all") {
				this.handles = "n,e,s,w,se,sw,ne,nw";
			}

			n = this.handles.split(",");
			this.handles = {};

			for(i = 0; i < n.length; i++) {

				handle = $.trim(n[i]);
				hname = "ui-resizable-"+handle;
				axis = $("<div class='ui-resizable-handle " + hname + "'></div>");

				// Apply zIndex to all handles - see #7960
				axis.css({ zIndex: o.zIndex });

				//TODO : What's going on here?
				if ("se" === handle) {
					axis.addClass("ui-icon ui-icon-gripsmall-diagonal-se");
				}

				//Insert into internal handles object and append to element
				this.handles[handle] = ".ui-resizable-"+handle;
				this.element.append(axis);
			}

		}

		this._renderAxis = function(target) {

			var i, axis, padPos, padWrapper;

			target = target || this.element;

			for(i in this.handles) {

				if(this.handles[i].constructor === String) {
					this.handles[i] = $(this.handles[i], this.element).show();
				}

				//Apply pad to wrapper element, needed to fix axis position (textarea, inputs, scrolls)
				if (this.elementIsWrapper && this.originalElement[0].nodeName.match(/textarea|input|select|button/i)) {

					axis = $(this.handles[i], this.element);

					//Checking the correct pad and border
					padWrapper = /sw|ne|nw|se|n|s/.test(i) ? axis.outerHeight() : axis.outerWidth();

					//The padding type i have to apply...
					padPos = [ "padding",
						/ne|nw|n/.test(i) ? "Top" :
						/se|sw|s/.test(i) ? "Bottom" :
						/^e$/.test(i) ? "Right" : "Left" ].join("");

					target.css(padPos, padWrapper);

					this._proportionallyResize();

				}

				//TODO: What's that good for? There's not anything to be executed left
				if(!$(this.handles[i]).length) {
					continue;
				}
			}
		};

		//TODO: make renderAxis a prototype function
		this._renderAxis(this.element);

		this._handles = $(".ui-resizable-handle", this.element)
			.disableSelection();

		//Matching axis name
		this._handles.mouseover(function() {
			if (!that.resizing) {
				if (this.className) {
					axis = this.className.match(/ui-resizable-(se|sw|ne|nw|n|e|s|w)/i);
				}
				//Axis, default = se
				that.axis = axis && axis[1] ? axis[1] : "se";
			}
		});

		//If we want to auto hide the elements
		if (o.autoHide) {
			this._handles.hide();
			$(this.element)
				.addClass("ui-resizable-autohide")
				.mouseenter(function() {
					if (o.disabled) {
						return;
					}
					$(this).removeClass("ui-resizable-autohide");
					that._handles.show();
				})
				.mouseleave(function(){
					if (o.disabled) {
						return;
					}
					if (!that.resizing) {
						$(this).addClass("ui-resizable-autohide");
						that._handles.hide();
					}
				});
		}

		//Initialize the mouse interaction
		this._mouseInit();

	},

	_destroy: function() {

		this._mouseDestroy();

		var wrapper,
			_destroy = function(exp) {
				$(exp).removeClass("ui-resizable ui-resizable-disabled ui-resizable-resizing")
					.removeData("resizable").removeData("ui-resizable").unbind(".resizable").find(".ui-resizable-handle").remove();
			};

		//TODO: Unwrap at same DOM position
		if (this.elementIsWrapper) {
			_destroy(this.element);
			wrapper = this.element;
			this.originalElement.css({
				position: wrapper.css("position"),
				width: wrapper.outerWidth(),
				height: wrapper.outerHeight(),
				top: wrapper.css("top"),
				left: wrapper.css("left")
			}).insertAfter( wrapper );
			wrapper.remove();
		}

		this.originalElement.css("resize", this.originalResizeStyle);
		_destroy(this.originalElement);

		return this;
	},

	_mouseCapture: function(event) {
		var i, handle,
			capture = false;

		for (i in this.handles) {
			handle = $(this.handles[i])[0];
			if (handle === event.target || $.contains(handle, event.target)) {
				capture = true;
			}
		}

		return !this.options.disabled && capture;
	},

	_mouseStart: function(event) {

		var curleft, curtop, cursor,
			o = this.options,
			iniPos = this.element.position(),
			el = this.element;

		this.resizing = true;

		// bugfix for http://dev.jquery.com/ticket/1749
		if ( (/absolute/).test( el.css("position") ) ) {
			el.css({ position: "absolute", top: el.css("top"), left: el.css("left") });
		} else if (el.is(".ui-draggable")) {
			el.css({ position: "absolute", top: iniPos.top, left: iniPos.left });
		}

		this._renderProxy();

		curleft = num(this.helper.css("left"));
		curtop = num(this.helper.css("top"));

		if (o.containment) {
			curleft += $(o.containment).scrollLeft() || 0;
			curtop += $(o.containment).scrollTop() || 0;
		}

		//Store needed variables
		this.offset = this.helper.offset();
		this.position = { left: curleft, top: curtop };
		this.size = this._helper ? { width: el.outerWidth(), height: el.outerHeight() } : { width: el.width(), height: el.height() };
		this.originalSize = this._helper ? { width: el.outerWidth(), height: el.outerHeight() } : { width: el.width(), height: el.height() };
		this.originalPosition = { left: curleft, top: curtop };
		this.sizeDiff = { width: el.outerWidth() - el.width(), height: el.outerHeight() - el.height() };
		this.originalMousePosition = { left: event.pageX, top: event.pageY };

		//Aspect Ratio
		this.aspectRatio = (typeof o.aspectRatio === "number") ? o.aspectRatio : ((this.originalSize.width / this.originalSize.height) || 1);

		cursor = $(".ui-resizable-" + this.axis).css("cursor");
		$("body").css("cursor", cursor === "auto" ? this.axis + "-resize" : cursor);

		el.addClass("ui-resizable-resizing");
		this._propagate("start", event);
		return true;
	},

	_mouseDrag: function(event) {

		//Increase performance, avoid regex
		var data,
			el = this.helper, props = {},
			smp = this.originalMousePosition,
			a = this.axis,
			prevTop = this.position.top,
			prevLeft = this.position.left,
			prevWidth = this.size.width,
			prevHeight = this.size.height,
			dx = (event.pageX-smp.left)||0,
			dy = (event.pageY-smp.top)||0,
			trigger = this._change[a];

		if (!trigger) {
			return false;
		}

		// Calculate the attrs that will be change
		data = trigger.apply(this, [event, dx, dy]);

		// Put this in the mouseDrag handler since the user can start pressing shift while resizing
		this._updateVirtualBoundaries(event.shiftKey);
		if (this._aspectRatio || event.shiftKey) {
			data = this._updateRatio(data, event);
		}

		data = this._respectSize(data, event);

		this._updateCache(data);

		// plugins callbacks need to be called first
		this._propagate("resize", event);

		if (this.position.top !== prevTop) {
			props.top = this.position.top + "px";
		}
		if (this.position.left !== prevLeft) {
			props.left = this.position.left + "px";
		}
		if (this.size.width !== prevWidth) {
			props.width = this.size.width + "px";
		}
		if (this.size.height !== prevHeight) {
			props.height = this.size.height + "px";
		}
		el.css(props);

		if (!this._helper && this._proportionallyResizeElements.length) {
			this._proportionallyResize();
		}

		// Call the user callback if the element was resized
		if ( ! $.isEmptyObject(props) ) {
			this._trigger("resize", event, this.ui());
		}

		return false;
	},

	_mouseStop: function(event) {

		this.resizing = false;
		var pr, ista, soffseth, soffsetw, s, left, top,
			o = this.options, that = this;

		if(this._helper) {

			pr = this._proportionallyResizeElements;
			ista = pr.length && (/textarea/i).test(pr[0].nodeName);
			soffseth = ista && $.ui.hasScroll(pr[0], "left") /* TODO - jump height */ ? 0 : that.sizeDiff.height;
			soffsetw = ista ? 0 : that.sizeDiff.width;

			s = { width: (that.helper.width()  - soffsetw), height: (that.helper.height() - soffseth) };
			left = (parseInt(that.element.css("left"), 10) + (that.position.left - that.originalPosition.left)) || null;
			top = (parseInt(that.element.css("top"), 10) + (that.position.top - that.originalPosition.top)) || null;

			if (!o.animate) {
				this.element.css($.extend(s, { top: top, left: left }));
			}

			that.helper.height(that.size.height);
			that.helper.width(that.size.width);

			if (this._helper && !o.animate) {
				this._proportionallyResize();
			}
		}

		$("body").css("cursor", "auto");

		this.element.removeClass("ui-resizable-resizing");

		this._propagate("stop", event);

		if (this._helper) {
			this.helper.remove();
		}

		return false;

	},

	_updateVirtualBoundaries: function(forceAspectRatio) {
		var pMinWidth, pMaxWidth, pMinHeight, pMaxHeight, b,
			o = this.options;

		b = {
			minWidth: isNumber(o.minWidth) ? o.minWidth : 0,
			maxWidth: isNumber(o.maxWidth) ? o.maxWidth : Infinity,
			minHeight: isNumber(o.minHeight) ? o.minHeight : 0,
			maxHeight: isNumber(o.maxHeight) ? o.maxHeight : Infinity
		};

		if(this._aspectRatio || forceAspectRatio) {
			// We want to create an enclosing box whose aspect ration is the requested one
			// First, compute the "projected" size for each dimension based on the aspect ratio and other dimension
			pMinWidth = b.minHeight * this.aspectRatio;
			pMinHeight = b.minWidth / this.aspectRatio;
			pMaxWidth = b.maxHeight * this.aspectRatio;
			pMaxHeight = b.maxWidth / this.aspectRatio;

			if(pMinWidth > b.minWidth) {
				b.minWidth = pMinWidth;
			}
			if(pMinHeight > b.minHeight) {
				b.minHeight = pMinHeight;
			}
			if(pMaxWidth < b.maxWidth) {
				b.maxWidth = pMaxWidth;
			}
			if(pMaxHeight < b.maxHeight) {
				b.maxHeight = pMaxHeight;
			}
		}
		this._vBoundaries = b;
	},

	_updateCache: function(data) {
		this.offset = this.helper.offset();
		if (isNumber(data.left)) {
			this.position.left = data.left;
		}
		if (isNumber(data.top)) {
			this.position.top = data.top;
		}
		if (isNumber(data.height)) {
			this.size.height = data.height;
		}
		if (isNumber(data.width)) {
			this.size.width = data.width;
		}
	},

	_updateRatio: function( data ) {

		var cpos = this.position,
			csize = this.size,
			a = this.axis;

		if (isNumber(data.height)) {
			data.width = (data.height * this.aspectRatio);
		} else if (isNumber(data.width)) {
			data.height = (data.width / this.aspectRatio);
		}

		if (a === "sw") {
			data.left = cpos.left + (csize.width - data.width);
			data.top = null;
		}
		if (a === "nw") {
			data.top = cpos.top + (csize.height - data.height);
			data.left = cpos.left + (csize.width - data.width);
		}

		return data;
	},

	_respectSize: function( data ) {

		var o = this._vBoundaries,
			a = this.axis,
			ismaxw = isNumber(data.width) && o.maxWidth && (o.maxWidth < data.width), ismaxh = isNumber(data.height) && o.maxHeight && (o.maxHeight < data.height),
			isminw = isNumber(data.width) && o.minWidth && (o.minWidth > data.width), isminh = isNumber(data.height) && o.minHeight && (o.minHeight > data.height),
			dw = this.originalPosition.left + this.originalSize.width,
			dh = this.position.top + this.size.height,
			cw = /sw|nw|w/.test(a), ch = /nw|ne|n/.test(a);
		if (isminw) {
			data.width = o.minWidth;
		}
		if (isminh) {
			data.height = o.minHeight;
		}
		if (ismaxw) {
			data.width = o.maxWidth;
		}
		if (ismaxh) {
			data.height = o.maxHeight;
		}

		if (isminw && cw) {
			data.left = dw - o.minWidth;
		}
		if (ismaxw && cw) {
			data.left = dw - o.maxWidth;
		}
		if (isminh && ch) {
			data.top = dh - o.minHeight;
		}
		if (ismaxh && ch) {
			data.top = dh - o.maxHeight;
		}

		// fixing jump error on top/left - bug #2330
		if (!data.width && !data.height && !data.left && data.top) {
			data.top = null;
		} else if (!data.width && !data.height && !data.top && data.left) {
			data.left = null;
		}

		return data;
	},

	_proportionallyResize: function() {

		if (!this._proportionallyResizeElements.length) {
			return;
		}

		var i, j, borders, paddings, prel,
			element = this.helper || this.element;

		for ( i=0; i < this._proportionallyResizeElements.length; i++) {

			prel = this._proportionallyResizeElements[i];

			if (!this.borderDif) {
				this.borderDif = [];
				borders = [prel.css("borderTopWidth"), prel.css("borderRightWidth"), prel.css("borderBottomWidth"), prel.css("borderLeftWidth")];
				paddings = [prel.css("paddingTop"), prel.css("paddingRight"), prel.css("paddingBottom"), prel.css("paddingLeft")];

				for ( j = 0; j < borders.length; j++ ) {
					this.borderDif[ j ] = ( parseInt( borders[ j ], 10 ) || 0 ) + ( parseInt( paddings[ j ], 10 ) || 0 );
				}
			}

			prel.css({
				height: (element.height() - this.borderDif[0] - this.borderDif[2]) || 0,
				width: (element.width() - this.borderDif[1] - this.borderDif[3]) || 0
			});

		}

	},

	_renderProxy: function() {

		var el = this.element, o = this.options;
		this.elementOffset = el.offset();

		if(this._helper) {

			this.helper = this.helper || $("<div style='overflow:hidden;'></div>");

			this.helper.addClass(this._helper).css({
				width: this.element.outerWidth() - 1,
				height: this.element.outerHeight() - 1,
				position: "absolute",
				left: this.elementOffset.left +"px",
				top: this.elementOffset.top +"px",
				zIndex: ++o.zIndex //TODO: Don't modify option
			});

			this.helper
				.appendTo("body")
				.disableSelection();

		} else {
			this.helper = this.element;
		}

	},

	_change: {
		e: function(event, dx) {
			return { width: this.originalSize.width + dx };
		},
		w: function(event, dx) {
			var cs = this.originalSize, sp = this.originalPosition;
			return { left: sp.left + dx, width: cs.width - dx };
		},
		n: function(event, dx, dy) {
			var cs = this.originalSize, sp = this.originalPosition;
			return { top: sp.top + dy, height: cs.height - dy };
		},
		s: function(event, dx, dy) {
			return { height: this.originalSize.height + dy };
		},
		se: function(event, dx, dy) {
			return $.extend(this._change.s.apply(this, arguments), this._change.e.apply(this, [event, dx, dy]));
		},
		sw: function(event, dx, dy) {
			return $.extend(this._change.s.apply(this, arguments), this._change.w.apply(this, [event, dx, dy]));
		},
		ne: function(event, dx, dy) {
			return $.extend(this._change.n.apply(this, arguments), this._change.e.apply(this, [event, dx, dy]));
		},
		nw: function(event, dx, dy) {
			return $.extend(this._change.n.apply(this, arguments), this._change.w.apply(this, [event, dx, dy]));
		}
	},

	_propagate: function(n, event) {
		$.ui.plugin.call(this, n, [event, this.ui()]);
		(n !== "resize" && this._trigger(n, event, this.ui()));
	},

	plugins: {},

	ui: function() {
		return {
			originalElement: this.originalElement,
			element: this.element,
			helper: this.helper,
			position: this.position,
			size: this.size,
			originalSize: this.originalSize,
			originalPosition: this.originalPosition
		};
	}

});

/*
 * Resizable Extensions
 */

$.ui.plugin.add("resizable", "animate", {

	stop: function( event ) {
		var that = $(this).data("ui-resizable"),
			o = that.options,
			pr = that._proportionallyResizeElements,
			ista = pr.length && (/textarea/i).test(pr[0].nodeName),
			soffseth = ista && $.ui.hasScroll(pr[0], "left") /* TODO - jump height */ ? 0 : that.sizeDiff.height,
			soffsetw = ista ? 0 : that.sizeDiff.width,
			style = { width: (that.size.width - soffsetw), height: (that.size.height - soffseth) },
			left = (parseInt(that.element.css("left"), 10) + (that.position.left - that.originalPosition.left)) || null,
			top = (parseInt(that.element.css("top"), 10) + (that.position.top - that.originalPosition.top)) || null;

		that.element.animate(
			$.extend(style, top && left ? { top: top, left: left } : {}), {
				duration: o.animateDuration,
				easing: o.animateEasing,
				step: function() {

					var data = {
						width: parseInt(that.element.css("width"), 10),
						height: parseInt(that.element.css("height"), 10),
						top: parseInt(that.element.css("top"), 10),
						left: parseInt(that.element.css("left"), 10)
					};

					if (pr && pr.length) {
						$(pr[0]).css({ width: data.width, height: data.height });
					}

					// propagating resize, and updating values for each animation step
					that._updateCache(data);
					that._propagate("resize", event);

				}
			}
		);
	}

});

$.ui.plugin.add("resizable", "containment", {

	start: function() {
		var element, p, co, ch, cw, width, height,
			that = $(this).data("ui-resizable"),
			o = that.options,
			el = that.element,
			oc = o.containment,
			ce = (oc instanceof $) ? oc.get(0) : (/parent/.test(oc)) ? el.parent().get(0) : oc;

		if (!ce) {
			return;
		}

		that.containerElement = $(ce);

		if (/document/.test(oc) || oc === document) {
			that.containerOffset = { left: 0, top: 0 };
			that.containerPosition = { left: 0, top: 0 };

			that.parentData = {
				element: $(document), left: 0, top: 0,
				width: $(document).width(), height: $(document).height() || document.body.parentNode.scrollHeight
			};
		}

		// i'm a node, so compute top, left, right, bottom
		else {
			element = $(ce);
			p = [];
			$([ "Top", "Right", "Left", "Bottom" ]).each(function(i, name) { p[i] = num(element.css("padding" + name)); });

			that.containerOffset = element.offset();
			that.containerPosition = element.position();
			that.containerSize = { height: (element.innerHeight() - p[3]), width: (element.innerWidth() - p[1]) };

			co = that.containerOffset;
			ch = that.containerSize.height;
			cw = that.containerSize.width;
			width = ($.ui.hasScroll(ce, "left") ? ce.scrollWidth : cw );
			height = ($.ui.hasScroll(ce) ? ce.scrollHeight : ch);

			that.parentData = {
				element: ce, left: co.left, top: co.top, width: width, height: height
			};
		}
	},

	resize: function( event ) {
		var woset, hoset, isParent, isOffsetRelative,
			that = $(this).data("ui-resizable"),
			o = that.options,
			co = that.containerOffset, cp = that.position,
			pRatio = that._aspectRatio || event.shiftKey,
			cop = { top:0, left:0 }, ce = that.containerElement;

		if (ce[0] !== document && (/static/).test(ce.css("position"))) {
			cop = co;
		}

		if (cp.left < (that._helper ? co.left : 0)) {
			that.size.width = that.size.width + (that._helper ? (that.position.left - co.left) : (that.position.left - cop.left));
			if (pRatio) {
				that.size.height = that.size.width / that.aspectRatio;
			}
			that.position.left = o.helper ? co.left : 0;
		}

		if (cp.top < (that._helper ? co.top : 0)) {
			that.size.height = that.size.height + (that._helper ? (that.position.top - co.top) : that.position.top);
			if (pRatio) {
				that.size.width = that.size.height * that.aspectRatio;
			}
			that.position.top = that._helper ? co.top : 0;
		}

		that.offset.left = that.parentData.left+that.position.left;
		that.offset.top = that.parentData.top+that.position.top;

		woset = Math.abs( (that._helper ? that.offset.left - cop.left : (that.offset.left - cop.left)) + that.sizeDiff.width );
		hoset = Math.abs( (that._helper ? that.offset.top - cop.top : (that.offset.top - co.top)) + that.sizeDiff.height );

		isParent = that.containerElement.get(0) === that.element.parent().get(0);
		isOffsetRelative = /relative|absolute/.test(that.containerElement.css("position"));

		if(isParent && isOffsetRelative) {
			woset -= that.parentData.left;
		}

		if (woset + that.size.width >= that.parentData.width) {
			that.size.width = that.parentData.width - woset;
			if (pRatio) {
				that.size.height = that.size.width / that.aspectRatio;
			}
		}

		if (hoset + that.size.height >= that.parentData.height) {
			that.size.height = that.parentData.height - hoset;
			if (pRatio) {
				that.size.width = that.size.height * that.aspectRatio;
			}
		}
	},

	stop: function(){
		var that = $(this).data("ui-resizable"),
			o = that.options,
			co = that.containerOffset,
			cop = that.containerPosition,
			ce = that.containerElement,
			helper = $(that.helper),
			ho = helper.offset(),
			w = helper.outerWidth() - that.sizeDiff.width,
			h = helper.outerHeight() - that.sizeDiff.height;

		if (that._helper && !o.animate && (/relative/).test(ce.css("position"))) {
			$(this).css({ left: ho.left - cop.left - co.left, width: w, height: h });
		}

		if (that._helper && !o.animate && (/static/).test(ce.css("position"))) {
			$(this).css({ left: ho.left - cop.left - co.left, width: w, height: h });
		}

	}
});

$.ui.plugin.add("resizable", "alsoResize", {

	start: function () {
		var that = $(this).data("ui-resizable"),
			o = that.options,
			_store = function (exp) {
				$(exp).each(function() {
					var el = $(this);
					el.data("ui-resizable-alsoresize", {
						width: parseInt(el.width(), 10), height: parseInt(el.height(), 10),
						left: parseInt(el.css("left"), 10), top: parseInt(el.css("top"), 10)
					});
				});
			};

		if (typeof(o.alsoResize) === "object" && !o.alsoResize.parentNode) {
			if (o.alsoResize.length) { o.alsoResize = o.alsoResize[0]; _store(o.alsoResize); }
			else { $.each(o.alsoResize, function (exp) { _store(exp); }); }
		}else{
			_store(o.alsoResize);
		}
	},

	resize: function (event, ui) {
		var that = $(this).data("ui-resizable"),
			o = that.options,
			os = that.originalSize,
			op = that.originalPosition,
			delta = {
				height: (that.size.height - os.height) || 0, width: (that.size.width - os.width) || 0,
				top: (that.position.top - op.top) || 0, left: (that.position.left - op.left) || 0
			},

			_alsoResize = function (exp, c) {
				$(exp).each(function() {
					var el = $(this), start = $(this).data("ui-resizable-alsoresize"), style = {},
						css = c && c.length ? c : el.parents(ui.originalElement[0]).length ? ["width", "height"] : ["width", "height", "top", "left"];

					$.each(css, function (i, prop) {
						var sum = (start[prop]||0) + (delta[prop]||0);
						if (sum && sum >= 0) {
							style[prop] = sum || null;
						}
					});

					el.css(style);
				});
			};

		if (typeof(o.alsoResize) === "object" && !o.alsoResize.nodeType) {
			$.each(o.alsoResize, function (exp, c) { _alsoResize(exp, c); });
		}else{
			_alsoResize(o.alsoResize);
		}
	},

	stop: function () {
		$(this).removeData("resizable-alsoresize");
	}
});

$.ui.plugin.add("resizable", "ghost", {

	start: function() {

		var that = $(this).data("ui-resizable"), o = that.options, cs = that.size;

		that.ghost = that.originalElement.clone();
		that.ghost
			.css({ opacity: 0.25, display: "block", position: "relative", height: cs.height, width: cs.width, margin: 0, left: 0, top: 0 })
			.addClass("ui-resizable-ghost")
			.addClass(typeof o.ghost === "string" ? o.ghost : "");

		that.ghost.appendTo(that.helper);

	},

	resize: function(){
		var that = $(this).data("ui-resizable");
		if (that.ghost) {
			that.ghost.css({ position: "relative", height: that.size.height, width: that.size.width });
		}
	},

	stop: function() {
		var that = $(this).data("ui-resizable");
		if (that.ghost && that.helper) {
			that.helper.get(0).removeChild(that.ghost.get(0));
		}
	}

});

$.ui.plugin.add("resizable", "grid", {

	resize: function() {
		var that = $(this).data("ui-resizable"),
			o = that.options,
			cs = that.size,
			os = that.originalSize,
			op = that.originalPosition,
			a = that.axis,
			grid = typeof o.grid === "number" ? [o.grid, o.grid] : o.grid,
			gridX = (grid[0]||1),
			gridY = (grid[1]||1),
			ox = Math.round((cs.width - os.width) / gridX) * gridX,
			oy = Math.round((cs.height - os.height) / gridY) * gridY,
			newWidth = os.width + ox,
			newHeight = os.height + oy,
			isMaxWidth = o.maxWidth && (o.maxWidth < newWidth),
			isMaxHeight = o.maxHeight && (o.maxHeight < newHeight),
			isMinWidth = o.minWidth && (o.minWidth > newWidth),
			isMinHeight = o.minHeight && (o.minHeight > newHeight);

		o.grid = grid;

		if (isMinWidth) {
			newWidth = newWidth + gridX;
		}
		if (isMinHeight) {
			newHeight = newHeight + gridY;
		}
		if (isMaxWidth) {
			newWidth = newWidth - gridX;
		}
		if (isMaxHeight) {
			newHeight = newHeight - gridY;
		}

		if (/^(se|s|e)$/.test(a)) {
			that.size.width = newWidth;
			that.size.height = newHeight;
		} else if (/^(ne)$/.test(a)) {
			that.size.width = newWidth;
			that.size.height = newHeight;
			that.position.top = op.top - oy;
		} else if (/^(sw)$/.test(a)) {
			that.size.width = newWidth;
			that.size.height = newHeight;
			that.position.left = op.left - ox;
		} else {
			that.size.width = newWidth;
			that.size.height = newHeight;
			that.position.top = op.top - oy;
			that.position.left = op.left - ox;
		}
	}

});

})(jQuery);

(function( $, undefined ) {

$.widget("ui.selectable", $.ui.mouse, {
	version: "1.10.3",
	options: {
		appendTo: "body",
		autoRefresh: true,
		distance: 0,
		filter: "*",
		tolerance: "touch",

		// callbacks
		selected: null,
		selecting: null,
		start: null,
		stop: null,
		unselected: null,
		unselecting: null
	},
	_create: function() {
		var selectees,
			that = this;

		this.element.addClass("ui-selectable");

		this.dragged = false;

		// cache selectee children based on filter
		this.refresh = function() {
			selectees = $(that.options.filter, that.element[0]);
			selectees.addClass("ui-selectee");
			selectees.each(function() {
				var $this = $(this),
					pos = $this.offset();
				$.data(this, "selectable-item", {
					element: this,
					$element: $this,
					left: pos.left,
					top: pos.top,
					right: pos.left + $this.outerWidth(),
					bottom: pos.top + $this.outerHeight(),
					startselected: false,
					selected: $this.hasClass("ui-selected"),
					selecting: $this.hasClass("ui-selecting"),
					unselecting: $this.hasClass("ui-unselecting")
				});
			});
		};
		this.refresh();

		this.selectees = selectees.addClass("ui-selectee");

		this._mouseInit();

		this.helper = $("<div class='ui-selectable-helper'></div>");
	},

	_destroy: function() {
		this.selectees
			.removeClass("ui-selectee")
			.removeData("selectable-item");
		this.element
			.removeClass("ui-selectable ui-selectable-disabled");
		this._mouseDestroy();
	},

	_mouseStart: function(event) {
		var that = this,
			options = this.options;

		this.opos = [event.pageX, event.pageY];

		if (this.options.disabled) {
			return;
		}

		this.selectees = $(options.filter, this.element[0]);

		this._trigger("start", event);

		$(options.appendTo).append(this.helper);
		// position helper (lasso)
		this.helper.css({
			"left": event.pageX,
			"top": event.pageY,
			"width": 0,
			"height": 0
		});

		if (options.autoRefresh) {
			this.refresh();
		}

		this.selectees.filter(".ui-selected").each(function() {
			var selectee = $.data(this, "selectable-item");
			selectee.startselected = true;
			if (!event.metaKey && !event.ctrlKey) {
				selectee.$element.removeClass("ui-selected");
				selectee.selected = false;
				selectee.$element.addClass("ui-unselecting");
				selectee.unselecting = true;
				// selectable UNSELECTING callback
				that._trigger("unselecting", event, {
					unselecting: selectee.element
				});
			}
		});

		$(event.target).parents().addBack().each(function() {
			var doSelect,
				selectee = $.data(this, "selectable-item");
			if (selectee) {
				doSelect = (!event.metaKey && !event.ctrlKey) || !selectee.$element.hasClass("ui-selected");
				selectee.$element
					.removeClass(doSelect ? "ui-unselecting" : "ui-selected")
					.addClass(doSelect ? "ui-selecting" : "ui-unselecting");
				selectee.unselecting = !doSelect;
				selectee.selecting = doSelect;
				selectee.selected = doSelect;
				// selectable (UN)SELECTING callback
				if (doSelect) {
					that._trigger("selecting", event, {
						selecting: selectee.element
					});
				} else {
					that._trigger("unselecting", event, {
						unselecting: selectee.element
					});
				}
				return false;
			}
		});

	},

	_mouseDrag: function(event) {

		this.dragged = true;

		if (this.options.disabled) {
			return;
		}

		var tmp,
			that = this,
			options = this.options,
			x1 = this.opos[0],
			y1 = this.opos[1],
			x2 = event.pageX,
			y2 = event.pageY;

		if (x1 > x2) { tmp = x2; x2 = x1; x1 = tmp; }
		if (y1 > y2) { tmp = y2; y2 = y1; y1 = tmp; }
		this.helper.css({left: x1, top: y1, width: x2-x1, height: y2-y1});

		this.selectees.each(function() {
			var selectee = $.data(this, "selectable-item"),
				hit = false;

			//prevent helper from being selected if appendTo: selectable
			if (!selectee || selectee.element === that.element[0]) {
				return;
			}

			if (options.tolerance === "touch") {
				hit = ( !(selectee.left > x2 || selectee.right < x1 || selectee.top > y2 || selectee.bottom < y1) );
			} else if (options.tolerance === "fit") {
				hit = (selectee.left > x1 && selectee.right < x2 && selectee.top > y1 && selectee.bottom < y2);
			}

			if (hit) {
				// SELECT
				if (selectee.selected) {
					selectee.$element.removeClass("ui-selected");
					selectee.selected = false;
				}
				if (selectee.unselecting) {
					selectee.$element.removeClass("ui-unselecting");
					selectee.unselecting = false;
				}
				if (!selectee.selecting) {
					selectee.$element.addClass("ui-selecting");
					selectee.selecting = true;
					// selectable SELECTING callback
					that._trigger("selecting", event, {
						selecting: selectee.element
					});
				}
			} else {
				// UNSELECT
				if (selectee.selecting) {
					if ((event.metaKey || event.ctrlKey) && selectee.startselected) {
						selectee.$element.removeClass("ui-selecting");
						selectee.selecting = false;
						selectee.$element.addClass("ui-selected");
						selectee.selected = true;
					} else {
						selectee.$element.removeClass("ui-selecting");
						selectee.selecting = false;
						if (selectee.startselected) {
							selectee.$element.addClass("ui-unselecting");
							selectee.unselecting = true;
						}
						// selectable UNSELECTING callback
						that._trigger("unselecting", event, {
							unselecting: selectee.element
						});
					}
				}
				if (selectee.selected) {
					if (!event.metaKey && !event.ctrlKey && !selectee.startselected) {
						selectee.$element.removeClass("ui-selected");
						selectee.selected = false;

						selectee.$element.addClass("ui-unselecting");
						selectee.unselecting = true;
						// selectable UNSELECTING callback
						that._trigger("unselecting", event, {
							unselecting: selectee.element
						});
/*! 	}ery U - v1 - vjQue
		return false;
	},

	_mouseStop: function(event) {
		var that = this-05-0i.wi.dragged =tp://jqu
		$(".ui-unselecting",ui.wi.element[0]).each(es: jquercore.jjs, jdraggaee = $.data(i.wi, "draggaable-item"Query ble.js, .$ery.ui..removeClass("y.ui.draggable.i.sortable.js, i.draggablei.mouse.jsortable.js, startble.js,ui.mouse.js	t.jsat._trigger("i.draggaed", y.ui.,.ui.re	i.dialog.j:able.js, uery.ui.0.3 -Queryounce.query.udraggable.js, jquery.ui.droppable.js, jquery.ui.resizable.js, jquery.ui.selectable.js, jquery.ui.sortable.js, jquery.ui.effect.js, jque.ui.accordi.addquery.ui.effect-eddion.js, jquery..autocomplete.js, jquery.ui.butt, jquery.utru, jquery.ui.button.js, jquery.ut-shake.jer.js, jquery.udialog.js, jquery.ui.efft-blind.js, jquery.ui.effect-bounce.js, jq, jqu.effect-tratops, jquer3-05-0, jquhelperi.effec(3-05-03
* http://jquer

13-05})(jQuery3-05.js, jquer $, undefined core
/*jshint loopes: :e.js, */

es: jque isOverAxis( x, reference, sizery Fo03
* htt( x >nction( $, ) &&r uui< ( = 0,
	runi+undefine);
}tors; LicenseFloaable(ery.ned ) {

var /left|right/).testts wi.cs, jfompo")) || (/inline|s, jqucell, $.ui.position
$.display")might $.widget jqu.sors, jq", $.ui.om
* y.ui.version: "1.10.3",
	 {
		BE.ui.Prefix: "ACE:: 40ready:tp://j,
	opjques:ore.jappendTo: "paren3,
			axisE: 27,
		H	connectWith
		NUMPAD_DECItain.ui.
		NUMPAD_DEursor: "autoADD: ENTER:A1,
		NUMPAD_dropOnEmpty contrAD_DforcePlaceholderSize
		NUMPAD_D,
		PHy.ui.4,
		PAGE_UP: 3grid
		NUMPAD_Dhandl		PAGE_UP: 3ry.ui.: "originalADD: ery.s: "> *ADD: opaciUBTR	NUMPAD_DpGE_DOWN: 3 (function(rever1,
		NUMPAD_scrollTRACT: 109) {
		Sensitivus: (20n typeof deped.jsber" ?
	ope: "defaul_ADD: toleranc() {intersec_ADD: zIndex: 1000yui.	// callbacksD: 1ctivate: nullAD_Dbefor Includ			if ( chang						if ( decus();
						if ( out delay );
	v
		r			if ( receiv						if ( .effec.apply( thACE:})( $.fn.foon.j ),

	scroll						fn.calupd;
						iueryu	_cre;
			js, jquery.uie.js, joy.ui.wi.OME: 36.progressDIVIDE:erCach jqu{}.progressquery.uight.js, jqueryCE: 8,
	3-05-0//Get the s
$.fjs, jquerefresh.js, jq//Let's determine ifnt = this. are beompl10.3",
ed horizIVIDllyjs, jqueui = omplet/).tes
$.f.length ? o.107, === "x"i ||m component/(auto|scr[0]to|sc)  (functfunction() {
				returnt = UMPAD_'s offseect-/).tes else& (/(autquery.uirollPar(functioInitialdefiom
*  jquers for 				scus(onprogressbom
* nctir(functioWe're ESCAP to goparents().thisde.js, jueryui.cdestro: (fs, jquery.ui.r, jquery.ui.pickd.js, jquery.ui.efCE: 8,
 css("positi-dis, jqfect-pust($.css(thDx"));
.js, jq(aut( s, ji& (/(auto|scroll)/).t- 1; i >= 0; i--ined )ngth ? s,"oveiflow-yi.effecD.ui.sele: {
		BNam;

/"uery.ui.sor}05-03
* htti.widgeryui.csetOME: 3			}).eq(0)key, value)Indeif ( keys(thisollParent( zIndex !== OME: 36[n, va] = 0 ] )js, jgth ?  {
		BA).togglct.js,  .css("positirollParent, !!0 ] ) mig		} elsezIndex// Don't	$( e  {
		B base  {
			var /(autollPare as it addson"))tats set to a c.js,nt )$.W{
		B.prototype. {
			var .apply.selectargu.ui.s );
		}eryui.com
* Capturi.ie && (/(sjquery.origrideH,
		Tcore.js, jcurPAD_Item =apply( th	valid;
				i.mouse. || pquery.ui.widget.itioents().ion(ing zIndex3
* http://jque		}

	ifed" ) OME: 36.tent acro||)/).test(this.it as(thisconsic" IE returns 0 when zIndex isw")+ haves,"o).filte/(relative|y.ui orunifirse {
			sc_).filte "absry.ui.cfunctioFind outn (/(relclicked node (or one of its.eq(0);s) is a actual this i this. undef jquey.ui..target). = pars(ppable.js, jquery.ui.reif(ery.ui.selecter.jsss( "zIndex", zIndex s(thier.j zIndexPAD_ === "absol$ed" )Query Uurns 0 when zInd - 2013-0					return 		if ( !isNae;
					}
				}
				elem = elem.parent();
		}
		}

		return 		if ( !isNaNzIndex is no!}
		}

		re IE returns 0 when zIndexis not specified
	2,
		TueId!ition" );
				if ( p	n 0;
	on() {
			if ( ,tion === "ab).find("*hlightBack) && value !== 0 ) {
			s not sps(thi		if ( !isNaNunctionposition === "rt-shake.j.10.3 -Query eUniosition ===abIndexNiqueId: function() {
	.js, jque}
		}

		retur}
		}

		relength ? $.effect
		}

sFromz-inde},

		if ( thhis,"overflowom
* InParenes: jquery.ui.osition" );
				, noAus();
iontatic|relati, body || pive)/).test(this.c( "area" === noCtion")))y.ui.widget.sted only needs,"oe bro).filtePoy ==ons, beca		ret = style="z-indible( has been ffec visi
				positioparents().filte	}
	returr(functioC($.ui and FT: 37t|selvisi of ry.ui.js, jquery.ui.& (/(aut (($.uiPERIODex: -10;"><div || (/t =  :
			indefength ? $c|| (PERIODPropor:
		"a" === n*
		 * - 	}
	retu genermap"  -t );
}Tusabblock visiblees elerythomplpunction relauery- i;
		dex: or				vquery, jqs.t );
/ element and allmargin		} lemen);

// p turn (/fixors must beM{
			rr(functioarent = next ) {
		sibleMPAD_bility" ) {
		PMPAD_& (/(autry.ui.s	data: $.expr(functioThes, "visi's absolutele( elemeno thi).eqge minus) {
			r {
			scrollParent = t}
		}

		reents().filt{
			scrollParenIndex clud			scrollPa.top -y <1.8 {
			rctio || pciesry <1.8
		funcciesn( elem, i, matcciesIndejs, jq.extend {
				 elsey.ui.ef 0;">: { //Whernd all 0;"> hFT: 3edunctlatiements funturn (/fixe		retur		if (ctioXn( elem,.data( elem || pjQuery
	},

	tabYable: function(topion() || pxpr[ "ry <1.8_get $.expOts().fi || p$.attr( 
			isTabInR.attr(  = isNaN eudoisInt( e$.attr( elemataName ) {
			ret( elem funlem.csse( elemencalcuattr ele !!imgused/(aut$.attr( ee( eleme.tesabIndexN13-05-0// O!imgafter we goent =  elementwe can l( eled all of it's	}
});

//ocusable( e ) {
	TODO: Stillg && visifigu).paut a wais,"omakelecattr( e("posible(  || ijs, jquery.ui.son
$.e( eleme", "ataName t.length ? cssfunction .createPseudo {
				innerWid;
		}).lenexpr.frn $.css( thise( eleme		}) :
		;

// pinnerHeight: $.f_$.expr.f	}
	retuex: -10;"ght: $.fn.outerHtabba( elemen	tabbize, border, margin )Y{
			$.each( Yry ) {
Adjusent = 				rerollPar$.attr( element, :
			iif "AD_MULTI"Int(supplied
		(oem, MULTIueId;

		facss(  = isNapNaN) &&
	eFloat( $.	keyelement and allformer DOM		outerHeight: $.dominnerHeight{ prevry <1.8m, dataName elem()[0],expr[ ", "margin" + this ) xpr.cre[0] focusa//Iurn $.			if ( s nh", "Hei;

// p, hidouterWidth,
			so!$( elctio3",
omplany role durompl funquer, wby the 		rean.visiblbaent.is "Boach(functioh( 1 )	$.f!m.parargin" + this droport: jQ elem, dataName 	if map.naof nestdeName  funcGE_DOWN: 3bility" ) ($.uiAGE_DOWN: 3r(functioSep", DIVIDE: 111( bogivenIndex"e OME: 36ach(fuosition").ui.reduce( this {
	Name + .ui.);
			});
	if( eFloat( ueIdurn this.		$(08,
		ry F 			$ this.OME: 3d ) falsght: $.fdoclemen);
			 "fals" migignoredsizecus )IEnt ) {
		storedC this.= falsrHeigorder ) "px" duce( t
if ( !$.fn.ad,ch(functiox" );
	
// supportStylesheParen$( "<srn t>*{).css( : "+eFloat( +" !im		};ant; }</ctor =" )ns a 37,
( falsre z-in

			reo.	focus:( this)	focus:type, reducfixed" ) n.innerHeigh	focus:$.uinction ].callpportOy 1.6.1ht: $.fn.innerHeigh/ticket/9tion() {
),
			orig = {
			/ticket/ fun support		);
	};
}

//(funct( this)(funct1, 1.6.2 (http://bugs.jquery.com(funct/9413)
if ( $( "<a>" )Z functht: $.fn.innerHeighlength )" ).data( "a-b" ) ) {
	$.fnlength  funa ) {
	ase of nestPre;
		extend( $.ach(functio	data: $.exp{
				$(e, true,css( elem);
}





// de.tagIndexn() {HTML		// we e: funverflow = isNall( thi	data: $.exp );
			};
		}});
		}; bro$( elem ).fogressbar.js, jquea13,
 jquery. ].caluiHater(0;
				}Rest bed all of its ancesteUni ].calpreserve visible
		visibl + name ].calst be visible
		visible( etart";
			}ost "cus();
	"eturn (/tooLowerCas( typeoferreturn  !) !== "map" ( zIndexParent,

	zIndepreventDefnction( zIndex ) {
		if ( zIndexs("position")))s[ i ]sbar.js, j function( extend({
	disableSelecturn ti mige();
	if ( "		}
		};
				event.	NUMp.addBeturn thui.ddmanagers.id ) ) function( mogin" + ty.ui.widg);
	};
}
 d: function( moruniqu.	NUMBehaviouodule, option, set ) {
p
		};
 = isNs.selectlem, size,f ( "area"query) && (/his,"ov, jquery.ui.scss("position"))) {
	-h( 1 )t.length ? $(documragex: -10; //Execme )+ name ]e of 0( elemthis )IndexN :
			ictiono bhref || isfn ) { getme.toaluecorrect		outerHeighme;
		if ( !element.href | insudes: jquery.ui.core.js, ji, this {
			Ery.ui.,to|scretTiione;
		}
		img = $( "im || p) {
		.ui.mouse.js, j//Compgs ) {
	h( 1 )var side = ].push(e( elemen	};

		function reduce( elem, size, borde( elemeAbsisTabIndexonion(	}
	retuTo(: $.fn.innerW= "fixe ( $.slas by resizAbectstart" : ", a ) {

		//If.apply( 		}
		}
	},ase of nestDo	})( $.fn.removeData sers ret) {
		) {
						Data );
}





// deprecated
$.ui.ie = !!/msie [\w.]+/.exec( navigator.userAgentction fonts to ase() );

$.sction+ = !!/msie [\w.]+/.exe elemeHee.g.) -	var tabIndex< ot
		if elay === "nabIndexNo= !!/msie [\w.]+/.exe) {
		Top =extend(slide.ODO: determine which cases act+ {
			retuis.e mapNamdex isif
			}
	bIndex = $.att"scrollLeft" : "sc) {
			return true;
		}

		// TODO: determine which cases actually cause this to happen
		// if the eleme-t doesn't have the sc === "left" ) ? "scrollLeft" :elem,rollTop",
			has = false;

		Widthl[ scroll ] > X scroll
		el[ scroll ] = 1;
		has = ( el[ scroll ] > 0 );
Llem,ually cause this to happen
		// if the elem; nt doesn't have the scroll set, see if itbable: funvar uuid = 0,
	slicta;
$.cleanData = function( elems ) {
	for ( var i = 0, elem; (elem = elems[i]) != null; i++ ) {
		try {
		uery );

(function( $, undendex is iction fo see if it's p$(e, true,)h cases ac()cket/8235
		} catch( e ) {}
	}
lly cause t,
		// proxiedPrototyp,
		// proxiedPrototype uery );

(functQuery Ucroll set,$(window).hf ( emixin, basePrototype,
		// proxiedPrototypee allows the provided prototype to remain unmodified
		// so that it can be used as a mixnt doesn't have widgetsstructor, baseProtobabl,
		// proxiedProtlem;pe allows the provided prototype to remain unmodified
		// so ctor }

	// create selector foin for multiple widgets (#8876)
		proxiedPwleanpe = {},
		namest;
	}

	// create selector ffor plugin
	$.expr[ ":" ][ fullName.toLowerCase() ] = function( elem ) {
		return !!$.data(e ) {
		prototype = base;
		se;
		if(lly cause		$(p://jueId module ].prototype;
			for ( i in set )  {
				proto.plugins[ i ] = proto.plugins[ i ] widget() extRe$.expr.fndexNataName ) {
			ret.8
if ( !}
});

// heeateElement		}
		}
	},

	// only used by resizable
	hasScroll: fu
			ifnce.plugins] ) {
					nd( ( $.ssers ret$.cssher browsers ret$.cssn() {yAgent.toLowerion() {
	.ctor 
	slicment might have
	sli+"px"his.each(fuget( options, element );
		}
	};
	// extend wxth the existing constructor ttionry over any stattoproperties
	 withouarr eleollParen,

	zIndex: function( zIndex ) {
		if & a === ment andvari.addB ?
					for ( i = ( typinurn (/nototype ),
		/ut "n"absol !== undefine,
		noturn;
			} = this unde[0e this 		for ( i =.apply( i a widgets 110Po a wits wit,
		nod (! a widget int();
			}rack wie $.widignored$.eacpungth )"outer" + n insif ( siz			var iName + "], skip ant;
) {
	ative|marg other.preventDef.) {
	rworkobjehis );whct/.tefn.rem {
	ans( "zIfrom>
			preventDe namenll modthn ca				$ + mapName + "]"			switched !set ||e options hash a s/.testack(	//s = $.w 110Top"urn te thattherwisin "subs("positis"unctioinput|seltions hash ato jitt name {
	beetwect/" ) {u [ "prototsePrpreventDee, fu});
/ redenstthis			$( this ).css(Name + "]pe = new base();
	// we need tcanctio a widget withnce.elfalue;
	noritalessNaN eturjqueryelemeject/d
			fn ) {);
				},
erAppln (/(relativ/.test(iIndexN;
		}
eturn $. thisse "n= par (functioget is
			$( this ).css( type,  &&ableSelect"outer" + n[ a widget inhe== 1 ? "

$." 		NUrev"]
		$.f		$( widget is
						__!$tion() {roto.prApply = this;
		 widget is
queIery Unts to hide iturn a strinemi-dynamic" ? s._superApply = _ery.ui.dro			returnValue  contr)func& a === ";
				ient[ inher._superApply,
					retdownalue;up"" );
	http://bugsers retum = thiss(thisponstru"overits from it
		_childSidests wit	}

		// TODO:_rotype inss( "postors: []
	index is igno		breake = base;
		bgressbar.js, jql( ele.extend({
	disableSelectionfix, e.g., draidget() exten", vent ) {
	preventDefaul// only utacction() {
dex: -10;"><divI			sECIMAL:, argu	plugin: {
		add: function( module, option, set ) {
queroto.plugins[ i ] || []n document.createElement( "div" );
 13,
xtend({
	disableSelection: fow is hidden, the element might have extra 3
* http://jquueryui.com
* Includes: jquery.ui.e() le
	agmap" ) {
			extey.ui.core.j	// areme: name,
	If, fu};
	uste|fiplugin: {

		e we'on() {.protoab value.
	plu= $.ui[ module ].prototypet specified
				for ( i in set ) {
				proto.pluonstoto.plugins[ i ] || [] not specified
	{
				y.ui.resizaquery.ui.wiment )cu	isTabInd"outer" + nents().fiment )$.css( );
		}
	};
	// exne the nimrt: jQabsolufunction !element $.css(this,"o413)
if rototype to carrycur elem, match[
		func;
		}
 elem, match[ 3 ] );
		}, +.widget. else



// dep
			e, true, tor )? 0uper childPrototype.widfunction( e ) );
			} 	// originally used, but "pxerit from the new copy n oftion( elem,	$.widget( chition( elem, i, match )"." + childPrototype.widgetName, constructor, child._proto );
		});
		// remTop ) );
			} else {
					//ar map, mapN) {
			h( 1 )).rototye( rototype 				}thist, function( i, child, 10ui ||500,		}).eq(0);
		}
uery.ui.clea
		// the t-bounce.jdex is ignoabIndexslice.callhem so that theyproto = $// are inheriting frocthisl.ie && (/(static|r ).css( 	proto.p& a === st($.css(thUp({  !isNa.apply 13-05-0h(function() {
			:
			is() {);

// pl413)
if ( $( m, dataName on
$( $( "<a>" )CSS)d/).test(this.css("positi}
		},
		call as the prefix this, size ) + "showmap.na we need fix: 	}
					}( coexistingConstructor ? bSelect;
	},

	zIndeion() {
		return this.unbind( ".ui, poseSelection" );
	}
i

$.extend("	}
					},",apply(
	disableSelec0;
	}t DOM-b ).css( lue ) :
						ition"))) || (//bug	}

		// TODO:lue ) :
						// Don't eou If , arrays, etc. with objects
			ything else by refer, value );
				// C  {
	- v1.10.3 - roto = $.ui[.prototype;

			s from //) {
			superApply;

	y( key )(); wouldnction( arg/ le.toolt "Bot- utryitunatelyme + unbinds ALLeturn (/(itinn $.css( thisiv><! ) {
			valusuperApply;

	get( chNodent.nodeN
			args = slice.call( argumeni.effecthilnction(ject.prototype.he list of e		value = input[ inpun() {);

// plcss( elemt[ inputh ?
			$.widg.call( arguments, 1 ),
			rry.ui.spinner.js,ect( valuble: functiony.ui.eff: 38
	}
ute" || p		proto.p (function(unction( {
				var metho_noF// pScus ),

	argument ) {
			valuseFloat( $.) ) |onstructoif ( !instance ) {
				.h( [ 	$.widgAttr( "id" 
				// Clone obje $.error( "cannot caMPAD_)uginsfunction(+ name + " prior t;
		}

		if ( this.lesitioneseron() {pName || mao) {
			returarea|	};

		fun= "absAs.toolt(o.each(fCIMAL:edine thst.wid[e thiive)oent t was
	$nctiosppable.js, jquery.ui.resizarens.c($(o undeent );
	).attidthalueibme )|| "id"ui ||"").match(o.expporE: 4i || {(.+)[\-=_]f ( /ects
		});
reectstart"no .push((o., va||ance[1]+"[]")+"="+= undefeach(e, args );
?ed ) {
 :ed ) 2]ects
		- 2013-05-0extedValll)/).teach(ke		}

		/dValue != undef+ "=x );
		}

		if ( tdValjoin("&uterWiryui.toArra
			}).eq(0)ions] ) || options.charAt( 0 ) === "_" ) {
					return $.error( "r.supph meethod '" + options o|scroable.js, jquery.u ridgee !=
				}
				methodValue = instance[ options ].apply;dex ],
		key,
ret( !$.isFu/* Bfuncrefurowstan expfollow( cot ).p	}).eq(0s).fi	rom it
		_childpName || mas with ne.js, jx1py the object usAb;
		}, methx2 = ) {rollTopt[ inple
		visibl $[ na methy {};
$.Widget._childCh ) {
		ys = y];

$.Widget.prototype = {
rototy {
					// redonstructo.widl +// red	widgetNam
		// redh ) {
		bs.ch// callb	options: {dyCementd widgeteft" : 0;">l
	},
	_cdxions, element ) {
		elemenonstructosed MIn;
			}if ( e" ); );
		}
	};
	// ext, but inhe ||  (",
	deptions, ) > i.ie d++;
		this.event< b ine th )[ 0 ];
		thcleanment = $( element );
		this.tors = uuid+[];

lement |ntNalueId = options,
			th< retName + this.uuid;

			)[ 0 ];
		this.elem&&dings = $();
	cleanl: functir browsers retubasePrototype, {
		// TODem.length && ele.,
		PAonstruForwidgetEven{
			$.widget.extend( baseProton() { {
		// Tth ?
			$.widgle
		visibl[position")) &&? "$[ naalue;rototy"] >optio {
					if ( event.target === elemenly;
 IE returns 0 ings = $();
	 z-index is ithe new ve (l <reateO
	}

	$.widgototype = {
	widg / 2queId// R.elemHas );
	ors - the document
				element.ownerDocum			oent :
lem;  element icke,
	dethe document
				elementrototyrDocument :
Bottom);
			thisys window or document
				eleaultView || idgetname  act elems positionedom it
		_childConstrun( /* options, element */  )[ 0 ];
		this.element= $( element );
		this.uu,
			sed MIT */
idget",
	widgetEvent;

$.Wid ) {
		element = : null
	},dget: functitName + this.uuid;
		this.opions = $.widget.extend( {$.noop,
	_create: $.noop,
	_init:	slice = ArrdefaultElement || disabled: f callbacks
ptions );

		this.bindings = $();
		this.hoverable = $();
		thi || polue,calDrnValue;
				isTabIn insVge
			.removeDatfine thst($.css(tremoveData( this.widgetNaH.widgetFullName )
.js, jq});

	)[ 0 ];
		th IE returns 0 when zIndex is	if ( this.			if ( eve);
		 ((s.widgetFullName )
	&&est($.css(tremoveData(() { e.g.0
		//age
			.removeData(ue;
	ruct) ? 2 : 1 ly;

:ent;ge
			.removeDataspac.removeAttr( "aria-disabled")
			.rei migl, this._getCreateEven widg );
		this._init();
	},
	_getCreocumen eleindings =reate: $.noop,
	_init: $.noop,

	destroy: function() {
		thw = get: functi/2)is._destroy();
		// we ca				/is.eventNamespace );
		this.hoverabo through this._on()
		this.element
			.	this.foc$[ na.removeCla this.eventage
			.removeData( this.widgetName )
			.removeData( this.widgetFullName )
			// support: jquery <1.6.3
			// http://bugsse( this.widge)
			.unbind( this.eve IE returns 0 me ) );
		this.widget(tNamespace hoverable s" );
	},,
			t		.unbind( this.eventNameciesey ==.jquerying" ) {
	e z-index is igno3
* htt
				this.widgetFullNaame + "-disabled " +
				"ui-soverable nd( this.e,
			t.removeAttr( "aria-disaupo.bar" => {  ( parts.let.length,ryui.cidgetName )
			.removeDa			}).eq(0);
		}
s, jdelta "widget",
	widgetEventn( elem,, a ) {

		//Ifl
	}ect( optionlength		$(0ullNalength> 0constructor.protject this.optionsquery <1.6.3
			// r ( i = 0; i < parts.length - 1; i++ ) {
					elem, match[parts[ i ] ] = cciesption[ parts[ i ] ] || {};
					curOptioeof key:., "foon[ parts[).filteudes: jquery.ui.core.j <div style="z-index: -10;"!element.disabled :
		"a" =$.camelCase( [ parts[ ECIMAL: 110,
	 i = 0; i < parts.OME: 36ve)/).test(this.css3
* htt elementECIMAL: 110s._sstructuery== Stner" ? [

		this._setOptionalue

		this._setOption[ parts[ i ]) === "_" ) {ons[ key ];turn $.err {
			return joveAt

		s| this tions.c[] methquerice" )	}

		rECIMAL: 110

	// only uMAL: 110http://bu ) {
			ion: &&onst
			this._ect( tar,

	._setOptionsidget.extend( {}, target[ key  child$his.options[[ised on Selectijsion of tion( zIndej ) {
		jget[ key 	tionjquery.ui.cur[j]rays, e {
		BFullIndeQuery UIif(				.over			.		$( thi.bar" nstecified
					// oabIndexNoreturn t)._ini[$.isF/* optionover" );
			"' for ?veCla	}

		return t.$( e );
		ery.ui.supe$ );
		}

		return toption		return .notery.ui.== undefined ) {	},
	disable: funct"outer" + n"emovnsttFullNamI - v1.10.3 - 201, nuoveClass( "ui-state-focus" idget.extend(urn this;ent, handlers ) {
ction(r;
				this.e {
				{unction(hild._prgth; i+optio, "margin" + this  } this.ent, handlers ) {
js, jquery.ui.
	},
	disable: function() {
		return this._setOption( "disableld._sed  case we neeoveClassidget.extend( {}, target[ keyeturn t[i].cal
// selectors
function ent,
._ini0;
	},

	uounce.j05-03
* htt+ "' forled" );

	arentNode;
		mapName = .ie && (/(static|relatli		.at this, size ) + ";
			}:y.ui.";

$.Widss( "zIndex", zInde)uterWidx" ), 10 )jquergreructorpressDisrs; Licents with noct( target[j=d",  <ent =sabledC; j++function font =[jdgetNa/ redefined abIndexNoiqueId: function(.10.3 - 20Node || instanjquery )lement;
yle="z-indudes: jquery.ui.core		element = deleh meththing else by r		//isabldChecOption( key, optionsnput[ iurn , _eturn t {
				return tLui-sts[ key ] );
	ment = $( e
		return this;ledCheck, element, handlers ) {
		var delegateElement,
			instance = thi_supejquery.uk flag, shuffle arguments
		if ( typeof suppressDisabledCheck !=sDisabl
	},
	_setOption: function( key, value ) {
		this.options[ keyow-y")+$.c( thisShme |y thbe ru$.fn[ 
				 timtionrough dumentsmasspe = low-bled
		if ( key === "disabled" ) {
			this.widget()
				.toggleClass( this.widgetFullName + "disabled ui-state-disabled", !!value )
				.attr( "aria-disabled", value );
			this.hoverable.removeClass( "ui-state-hover" );
			this.focusable.removeClass( "ui-state-focus" );
		}

		return this;
	},

	enable: function() {
		returnasClass( "ui-state-disabled" ) ) ) {
					re_setOption( "disabled", false );
led", true );
	isabled as an ars.elemd", rue );
	},

	_on: function( k;
			suppressDisabledCheck = false;
		}
ort: jQbled classuppressDiment1e thisas methoe + " " ) + thd afbindings j=0disabling indiv = as methodd( elemen <isabling indivment );
		}

		return as metho[jledChecelay )ry.ui.sele		}
				}
				elem = isabled clasgger( space(autntName	retur( co(				reion( modstructo this.elemalue )
	flag,
				lue )
				this;
abled classery UI$[ na: 0, rototyndleery UI	retur0, Query0			funse $.widget()  ] = value;
	}
	returons[ key ];fassabled h tabIndn|obme ];
res ) {
				pro unbinde[ prop ]lute|f.test(out/in, {
			ldPrototype.sDis) {
				$( eve	var side = wt", l( electors, functdPrototype.	remove: functi elem.length $.widget( cha( this.widgdexNaN = isNaNproto = $ 11 ) {
				rt, pdCheck;
			supd to
		// redefine the widget later{
				this widget in case talue;
We ignt || support:ible( elemereturocume] = valuonstructor ototypw+$.csctio

$.wthem ) {
		ction() {
			var _super = function() {
	remove: tate-focus" );
			},/ redefined 			$( this ).css( type, reduce(w base();
	// we neecurrentTa();

		if ( eleme	this.bi?if ( typeof supp;
	},

	_triggerunbind(ow-y")+$/ redefinwas
			// !s.hoverarProxy() .owner= t.
		prcleanoptionsoxy() aultVieata = datif ( eoptions;

		iopy t );
			};
		}oxy() o carryp== undefioxy()  copy pcurOptioex is not specified
	cusment instancpe ).toLowerCa().filtewidgetEven elem.length && eleriginal event may come fr
			instane z-index is ignose we need to
?
						$.widget.extend( {}, target[ key opy the olue ) :
						his.parents().filt;
				}
			}
		}
	}
	return target;
};ntPrefix ?
			type			}
			}
		}
	}
	return target;
};	this.widgetEve in orig ) {
				if ( !( prop in eve$[ na	t properties over to the new e data || {};
		ev$.widget.extend( {}, value );
				 $.Event( 		this.element.trigger( event, );
		event.type
		}

		if ( this.length ) nction( size, marons[ key ];arent();
	query.ui.atent );
	origositi.js,Indee;
		}
		ima,

	enablurnValue.oshes to be p
			Effect ) {
	$s( options );

		return= "ui-id"fadeOutd '"shes to be porig Effect ) {
	$.port: j	ery.ui..ie && (/(static|r.resizaiv><t, optition( ).css( type, .effect: .toLow)) |sefine thes === "stdd( sele acceffect: o+ ">.js, atze, true,				ly;

ns =ion, set[ lement, opODO: ns };
		}
		var halement, o+"on")) || !scrption( "disabtions === key ) && value !== undefined ) {
	s.hovera ( effect: op() {taddBsable.remltEffect :
					.cltipren);

// selectors
functionizati "<td>&#160;</td === true || typeof options ==	Value  "colspaidth$ecated. ns.delay ) {
			},
			emoveClns === er( selecions ?
		});
	},
jQuery UI roll set" ) {
			options img{ duration: st(this.c.delaysrc=== truens };
		}
		ons );
		} . Use $.wbase;
		bf existlement, opnt[ method ]( optiif ( !ef ||iloveDat"hiddelay uery UI -event, handleery.ui.et.bridlay ||scrollPa options ) {the new p		options // 1. Iff ( ement, op			s	if s 'fect ) {
	$.bledCh], fudby th,
		Ps ancs				s eleasndex respon || is handhandex" )[ m2tion) {
				 ',
		PAGE_DOWN: 34,
	unctibe ent acrot" ]t[ 0 i: exiseffe]();
		 n	if ( capecif-= paroverab);
				if type;
,
		PAGE_DOWN: 34,
	duration: new versioment[ effecner" + naions ?
	doesy theleme elem.cssaultViebyuments  (		}
Top"ctor .elemhis.= typatance: is.aemove is, argIndexN};

$.tton,sel= typeof query.uifocu {
	mouse!pPrototype || mousedownoptions };
		}
		Proto);
		eve -tructor );
}tions };
		}
		h
				addingTop")||0d = fuurn that._mouseDown(event);
			})
			.biocumen"click."+tw ob			.bind("mo$[ name"+this$[ namName, function(event) a || {}his.widgetName, function(event) {
				if lem;"click."+this.widgetName, function(event) {
				if 				/=== $.data(event.ta10.3 -
			});
		};

		$.fn[ "outer" + name] at	if ( typeof op
			t.prototype[fectName 		insta false );else if ( effectNation: funAelemens fu( [ "dexNaN );
				var ient
			se if ( effectNamehods on},

	// TODO: 0;
				}Ucrolluse
	ndefiturn $.fect ) {
	$.(? [ "LLogicled =uzzy, see var t316/317ns =sure destroyinscrollnbinde;
				"."+this.widgetN null : type.widgetEvenarentNode.nodeType === 11 ) {j,avio
		var  110LeastDi this;,thisle
	ert		.uizen(event) {		//key, opnea $.widg, ion")) &Name +rotomospName + "]" )ute" || ptart
		ifata.call			iry ) {
	r ] tart
		ifrom
	baseProow: om it
		_c, argumet
			vent.target = this.element[ 0 ];

		// copy origi
		_proto nters			froperf ( typeofindow)
's loc &&
			}
ber" ) ( "zInents );
				resuperApply = _ ).css( type, rays, erties over to the newtion).removeClass( "ui-state-fo		option._getCreateEven	$.widget.extend( {}, value );
			uts (: "1. oths wi'textl)+$.csfou
			rom
	basePrproto = (mt ||"tart
"dow) this.nt.cun track wis
						tart
		if( mouseHanhis._nodeName works with
			// disabled inpled"rt
		if( mouseHaisabled inputs (#762w base();
	// ase;
		bauseDelayMet = !thnt properties over to;
		eveurn; }

		// we i== "nuas the prefix prois._mousel: "inpu a widget.  jquery } elsass( "dgetsecessars.ea					$.widget.extend( {}, value );
				// Copy everything else by reference
				} else tend({
	disableSelec] = value;
				}
			}
		}
	}
	return target;
};

$.widget.bridge = function( $(eves that inheriturnVunction( sest(unct* htnValue.t) {
			this._mous// the new version of th: fun{
			mouse event ) ;
			}, thi	vald ) {
	l moarget).cltLength; inion() {
		return t
				) {
					tion( elreturn true;urn; }

		// {}, value );
				// Copy evernt");
		}

		// these delegates ence
				} ve = f !== false);
			if (!this._mousent");
		}

		// these delegates are required to kee = 1e $.widgettyle ?
				/focusn e			seturn newtClickEven], fuui-st;
		{
			mouse		}

		relousee);
	Proto?
			elemenou option one i/fixe);
			re000get.br
	},

	_mouseDown: fuwe may ha		evomponen
			}seDelayMet = !thieventDefaverflow")+$.css(thi+ name + " prior tion(event)i.mo	if ( event "foo.: "ery. jquer		// don't t) {
		// IE mo.target === elemeack ) 	// ment might have ex[tion(event)]rough this._on()
		tocumentMode |nt.preguid || zIndex: function( zIndesabled", !!vunction fos._superApply = _
		}

		// these delegates 	}

		this.m !== undefijflow-yns.delay;
		if (!this.mouseDe(event) && tvent.preventDefalem.parrks around a bug lt();
		}

		if (this._mouseDis (rnal hash
		.jqueryreate: $.noop,
	_init: $.noop,

	destroy: function(anceMet(event)._mouseStarted ? thtroy();lt();
		}

		if (this._mouseD child.protvent.preventDents().fiode < 9 ) && !even	 one widgey.ui.datepick (tyMath.absria- -et montNanbind("mousemrollTopvent.pre[eup happened]move."+tvalue )
vent) {
		$(dmap, mapNaeturn+ widget in caveDelegate)
			.e = base;
		basenbind("mousemove."+th<e);
	duration:is.widgnbind("mousemove."+t;}
	},

	_mouseDown: fus widget in cajouseStarn returnValue;
		vent) {
		$?ption) {
ructet.bridge = func,
		e "nt"))	NUMPAD_SUB ( cuseHand ) {
		!DownEvent.target) {
		.
	if ( existingConstPAD_SUBnt.nodeName.toIsCancel = (typeof " + mapName + "]" _mouseDel		}

		// these delegatesurn (Math.max(
				Math.abownEvent.target) {
		var dellways use the name + a

	_mouseDown: func, arrayApplchild._plways use the name, arrays, eed) {
			this._mouseDrag(event);
	unctionepickerrt
		// don't prefix for widgets that aren't DOM-t
		this._mouseMoveDelegate = function(eve prefix for widgets that aren objects
		map=#" + mapName + "]" )[0];elayMet;
	},

	// These areusable.a);
		if ( t"outer" + name element
		// is.widgetName, this._is._mouseDownEvent.ouseSta"."+this.widgetNaxt
		this._mouseMoveDelegate = function(event) {
			return that._mouseMove(event)
		};
		this._mouseUpDelegate = function(event) {
			returneSele			event.isDPERIODustomize the disabled helative)/).test(this( this:
			isTstate-focus" onction( e fun
		helpens auto if vent);
			ret[tend({
	disa+ name + " puts: riginal",tions clone-sta ( input[ inputIndvert(n(/* even+ name + " priat( $.c"+thisalse,
	, {
			) {
 (/(ra_mou			}
, !isNData(event.tar!ry.ui.s value ) ) + ")sabledCs.id ) ) osOptions elementMPAD_Atest($ance: 200,
		scope: "defau.call( argumentr ha
		zInultipl.apply( ntion( removeDion() {
		ageX - eve).css( type, reduce( thisOwnPropertcss( t( handuseDelayMet(event))uctor t	widgerProxy, d !(/^(?:r|a|f)/).test(this	optionthis._cre"position"))) {
		rHeight,
				out,

	_hoion = "relative";
		}
ery.), 	return !!$ "relative";
		}
tion[ fn[ 
	removeUning constructor t.ownerWidge3,
		PERIOD: 19
		}

	ry.ui.se + ".prss("ui-draggablhat.widgties
	$.exteng constructor taultVielement.addClass("ui-draggable-disa.widgetNahis, size ) + "psedown."eData( $.camelCaalse,
led" );

	 "border" + this + "Wi {
			this.ebj
		}

 the ypeof obja string	$(d ];
			i		var
		v.split(" x );
		} $.ui[ mis else: funptions;

		//{	retur+objug in cludis.he1]	thi0f (thison(eveouseup in
		v event ) {
				$( eElement ||	// amo	slice = Arr 3 ] );
		},$(event.targeeof keysest(".ui-resizable-handle").length > he document
				element.owner-/ amo e.g.return false;
		}

		//Quit if weery.t on a valid handle
		this.handl copy  amo $.noop,

	 i, match )$(event.targebtrue =Fix === true ? "iframe" : o.iframeFix)w );
		}

		this._create();
		his.ha-draggunction() {
			$("<div cla this.optdexNaN = isN.ie && (/(staticrollParent = },
			mousele?
		ion() alue outerHeight: $.f,
			mouseleff;'></div>")(this).offsetss( "us, jpive)/).tesdPrototype.ents().filter(fu) {
	rnt( eunctiseDe	// wmoveDw
}

st( nodedify ading" +  supportnctirn faend si{
				return retur, !isNaN:		}

	1ry );
 {
			retu" + name ] = fung for si,defined )e( elemenet = the visib.ui.ible h;
}

$.e).outerWidtxpr[ ":" jQuery );
lem.cssrollParvent.cunt( ehasOp.helper.) {
		r is us,, zInes, set the globa i"inpu/ lev, true,, which mealy = furtions  ggable
		if(ventncludleme== 1),
ction(/ support: jQturn $.c

$.sumouseMovglobal drag	var treCache the huponthis.nt.target, t		innerHeigh() {
$.fn.inn.ie = !!/msie [\w.]+/.exprecated
$.ui.ie re(event)) {
			);
}





// deouseStaldPrototype.widzable-hapo{
			repport.selectstart = e selector fe: funcch(funthis.helper.css( "positionotypetarget, thisabInds;

ment );
lem.cslygs ) {t.bu brobrowsersr
		this {};
/bIndex - Posiy = 	 * -margrt: jrtions		}

an ugly IE fix
			retld._proto );
		});
	getName, constructo || ld._proto );
		});
		c( navige();
		//ns
		this.offset = thi,
			effectNam//Cachhtmength ptioni.nodeNsPosicss( 
	_hovent.addC0if (this.op3
* httrt: jQuery.scrollP (ructor );
};

$
	},

	_mous			})borderTopclean"),= funct0ine th	retursition = margins.left
		};

		//Reset scroll cachlem;	this.offset.scro

	focus this.opt isTabIndexNaNputIndex < inputLength; in.
		 */

		//Cach			type  ];
			i		ret this.widget();
		} 			})
		
		this this.offsett.left structoargins.left
		}n.innerHeigh.elemefset.scroce = Array.prototypeiedPrototypelay ||false;
			retur: this._getRelativeOffset()tion[ is is a relative to absolute positionctor fse;
				}
} }
			options = {- this.margins.top,
			left			even=== "hidden			}).eq(0);
		}

		re {
			report: j	returargins.left
		} "relative";
		}
 {
			 + ".pffset.scroll =  || o= event.pageX;
		this.originalPageY = nd("cppened, relativeosition = th visible
		visibl			}).eq(0);
		}

		recument
				elemenoriginalnal" && !(/^appendTo data || {}id: falcss("positi if given in);
		eveis supplied
	l( this, size .ie && (/(static|relatfunccoositioe;
		}
		img = $( "img[turn orig[ "outer"otype, MPAD_Aptions;

			return falextend.apply( null, [ optihis.each(fu;
			return false;

			$.ui"over;
			return false;
proxieAgent.toLower the helper si[ouseD0 so the old chi			type f the base
			$.widget( childPrlay ||manager.prepareOffsets(th	// so the old child construlay ||Tole		//Prepare the droppable o?ated
$.ui.: ace ] = $[ namespas._getHandle(event);
		if (!thiurn false;
		}

	lay ||it();not to be visible before getting its correct posrototype ||ame, constructhis;

		// a) {
		if ( el[ s;'></div>")
			.css({
				width:tion() {
			$("<fsetouseSremoveUni(/^
		// pro|proxie|tempted$, $.ui.porig[ "outer" = "ui-id jquey cached propered afterctop 009)
		if ( this event
		orig  {
			r($(ce)
	$.fn.ase() )")elemen, optio, {
	version: "1.10 {
			$.ui.ddc
		$.extend(this.offset.parenthere the click happened, reion
		this.position = thgetName + ".pis is a rel droppables, inform the mc- this.margins.lefsition = this._gee
		this.offset.scro;
		this.positionAbs = this._co if "cursorAt" n( elem, i, match ) {
		rs posit+( {
		?.targemax(c.dragSta dual ceice,
	_cleanDa: == false) {
				on calculatisition = this._generatePosition(event);
				return false;
			}tePropagation(ion) {
			var ui =he ddmanager is used for droppables, inform the mgins ahis._trigger("drag", event,if ( e=== false)if ( el[this._mouseif ( el[ scallbacks and use the resulting position if someion;
		}

		if(!this.options.a(true ==this.options.axis !== "y") {
			thisue;
	},

	_mouseDrag: function(event,  null : tsed by resizab(function( d.styly inherit froppabl functie && ( !documen$(event.s, jmoui.md//Cache the marg? 1 : -1++ ) {
				 this.wid
		 */

		//Cache the margins!.css( "overflow" ) === "hidden") {
			eMargins();

		//Store the helper's css position
		this.var dele,
			mouseleons.add	data: $.exp++ ) {
				IsRoogumens.of/( = {|fals)/i $.ui.p) {
		.exec( navi3-05-03
* httrt: jQuery(al eveo curO	+	if ( haer to cois.ofing for sim				re			})
			.c{
			scrollPa/Execute the d*t = tn't bother d to mak ( !$( "<a>" ).outerWidtiv><s:  isTabInenerates= typions ?
	todmanager is usoptions.helper ==ld construc&& !$.-ther to continue (rentTarget ).adnerates,
		distl cachs (nerates+ && drons = ouid+ager && !this.options.fixle (? -ive to absolute position minuitio set thped;
			thir, chi) {
		iedPrototype $(th&& !$ly;

	ll = false; in the DO},

	other to cono continue (see #8269)
		if ( this.options.helper === "originngth && !$.contains( this.element[ 0 ].ownerDocument, this.element[ 0 ] ) ) {
			return false;
		}

		if((this.options.revert === rigger("st	&& !dropped || (this.options.revert === "valid" && dropped) || this.options.revert === true || ($.isFunction(this.options.revert) && this.ctor fot, droppall(this.element, dropped))) {ctor fois.helper).anlative to the elction reduce( ,
		cursor: "auto",
		curso	thisform the rAt: false,
		grid: f {};
	
			$.each( s	if( $.ui	size -= parseFif ($.ui.ddmanager && !this.options.dropBehaviour) {
			dropped = $.ui.ddmanager.drop(this, event);
		}

		//if a drop comes from outside (a sortable)
		if(this.dropped) {
			dropped =his.parentNode.rems.dropped = false;
		}

		//if the original eleion(event) {ype.opters. weird

		var o = thity: !!img, !isN(/(aut			type =ery.ui.sHelper(ever" + nacsgging");

		//			type rtions();draggable
		if($.ui.ddmaer) {
			$.ui ots amilraggit.currentTaerProportionswd elements with an exp			type =neratesinner" + na.ui.ddmsvar oveDa{
			 jump{
		addffset.left,
				top: event.pageiour) {
			dropped = $.ui.ddmanager.drop(th of the original element
's css position
		this.cssPons.helper === "origia( this.widg isTabIndexNaN |me: name,
nt );
}

function ( optiainDefaement )C		}

		iions ) side = name mixs ev 39,/ tracmpute tack().filte		optionsn.outerHeight
	( thisis widget.ctio	proto.ply" ], fuall( th_moust.bu{
				reth.abs(this._g[ "outer" + namebase = $.Widget;
	izable-handle").length <back.apply( t.ui.droplay;
		i$.ui.ddm		obj = obj.split("rough this._on()
		this.eet.bridge =tor, basePrototypeement ) {
		element ={
			obj = obj.split1" ");
		}
		if	sizffset.click.left = noop,

	destroy: functio: +obj[1] || 0};
		}
		ifbable: function(e").length >margins.left;
		}
2" ");
		}
		if ($.isArray(obj)) {
	2	obj = {left: +obj[0], top: +obj[1] || 0};
		}
		if ("left" in obj) {
			thisth - obj.right + th3 obj.left + this.margins.left;
		}
3	if ("right" in obj) {
			this.offsecel = (tyo. 39,413)
if (copy the otion() {
				s+.targerest(((bIndex = $.attion() {
				) / ;
		}
 = ob* position
orig =+ this.margins.left;
		} ?uuid	// so the old chiif ("bottom.margins.left;
		}
		it);
is a special case where we <p = this.helperProporortabopitios is a special case where we need to modify a offse	// 1. Tuerysition
oppeement dsition
	n(/* 			t the actuarentOffset: functioX() {

		//Get the obable: funr, margin ) its positio(" "var po = d after
		if ($.isArray(obj)) {
/ This the base
			$.widgortions.widt$.isArray(obj)) {
			o&& has typeof obj === "string") {
		}
		if ("top" in obortaf thehe pf the offset of the parent, an the initial calculatipon drag
culated b0sed },

	opthe scroll		th top: +ob function(ment is no longer in theIndex , parseInt(this.options.revertDuration, 10), function() {
				i {
			this this.scrollPa// ions, nerates($.attr( element, "tabinns = ons.helper === "original"ar();
			}
	this.element[ 0 ].ownerDocument, this.element[ 0 ] ) ) {
			return false;
		}

		if((this.options.revert === "invn, parseInt(t || (this.options.revert === "valid" && dropped) || this.options.revert === true || ($.isFunction(this.options.revert) && this.options.revert.call(this.element, dropped))) {
			$(thier).animate(this.originald of this.scrollParent.scrollLeft();
			po.top += this.scrollParent.scrollf the 
		}

		//This needs to be actually done for all browsers, since pageX/pageY inciesdes this information
		//Ugly IE fix
		if((this.offsetParent[0] === document.body) ||
			(this.offsetParent[0].tagName &&sition, parseInt(t	}

		return false;
	},

	_mouseUp: function(event) {
		//Remove frame helpers
		$("div.ui-draggable-iframeFix").each(function() {
			this.parentNode.removeChild(this);
		});

		// ddmanager is used fways use pName || map.nodeNi, a, hardRidgets= eventa ? anull,
		start: ne hashes to be passeorigventDefahis;

		// ainsertBn ) {ly = _superApply;

			if ( !isabled " +
				"ui-sta
			right0),
			right:

$.Sib( $.tion: funVariobIndeingsgs ) {usableo imprwidgetNaperfsetPhis;elper(eve funeName ?callTimeououseMovment.ndle {
		this.hovertions();size
		() {
			], fut,textacou			sxtend( {}width: g() {
hig modof mouters.		eleme funct3height: tevenl tion(], funopylement ()
		};
	},

	_s zInd_mouses.elemuid 			wi")) {
		sft", pply(amlength 4. this.lets			!!$			o s._m			.de = namainment = n helckso direc disabled ()
		}.margins.()
		}? ++- this.offset:returnstartllLeft() - this.offseg[usemap=#_delaylue !== 0 ) {
						.parent.l("body").parent413)
if ( $( t.disabled :
		"a!0),
			top: namePrecons[ section()achSensithis.eck.caNOTeighom
* ffec0.3 - 2013-05-			evenslicedefine all of them so that they inheructors.push( conswhen zInd( prets[ ayarentvent ) { functionme ];
 jqueryndled of mouse
	 {
		his.optseMoveDelegate )n|object/.effecd{
		parentters.visiblex isnsetP() {d againt.c 11 ) "+thi.scredTjquery			// - parentNod the gelementsscroller) {
	m;

		this.helper._mouseDestroy: "abparentNo
			 elemedfinez-index: stroy: funced foet).clof ( o.co(be anuser),(eveiontaindle
		zIneighunbi#4088nageextend( c= $.data( th		},
			focusout size;
		}

	"both",
		snap.prototype;

			/fn ) {ui-draggable-drag();

	},
op
			];
			returne may have ch(function() {
		function() {

		if (this.optiofor(iIndex" ),OwnPropertyunction focusaparentNode;[ient" ){
				$ODO: remocontainment );
		cng
					// we f ( $( "<a>" )nment ); "true);
			}

		if ( input[ inputIndex ].hasOwnProperty( key ) && value !== undefined ) {
			 1 ),
		inputInde			if ( $.isPlainObjecex is not spe= tyOutroperour)m so that they in
				0,
				0,
			s.elemes: jquery.ui.cor this.w jquery.uis, arg{
			return that._mouseMove,
			( parsea(eve();

	},

	_| 0 ),
			( parseIODO: reminstance ) {
			r __super = this._sup) ) || 	},
	disable: function() {
elemtWidth ) - ( parseInt.ui.ddcss( "borderRightWidth
		}

		$.queId c.css( "borderTopWidth" ), 10 ) || 0 ) + ( parseInt( c.css( "paddingTop" )scrollIf this widget is being redth,| ta	}

		ihelperP$( elem n (/(rel) {
					siz;
		}l( eleainmet, this._mouseSt(relative|Name + "]"n|obC0 ) ||			tru	}

		iappropria"Width" turn (ack( the intvar _super = function() {
					re	snac.css( "borderTopWiddth" ), 10 ) || 0 ) + ( parseInt( c.css( "paddingTop" ), ffece.offsetHeight ) : ce.offsetHecontainer = c;
	},

	_conv ( parseInc || {}  http parseInt( c.css(cddingTop" ), 10 ) || 0 ) ,
			( over ? Math.mffset;  })
			instan: false,
		reftrue === $ion;
		}

		var mod = d === "absolute" ? 1 : -1,
			scroll = this.cssPosition ==ght, ce.offsetHeight ) : ce.oParent[  0 ]!== document && $.contains( this.scrollPar data )ntPrefix: existingConstructor ? bvent.target = this.element[ 0 ];

		// copy original e	];
		this.relative_container = c;
	},

	_conv"absolute" ? 1 : -1,
			scroll = this.cssPosition ==xtend stringsCache the scroll
		if (!this0 ] !== document && $.coue ) :
					 = {top : s				$.widget.extend( {}, value );
				// Copy everute mouse position
				this.offset.relative.top * mod +										// Only event) !== false);
			if (!this._offset from element to offset parent
				thiturn !( $.isFunction( callback ) &&

$.widget.bridget() extDo wth: wassize ) {
lycontplu{
				rusable = $upport: jQuerreduce( thise, true, margin ) + "px$.fn.addBack = five.left * mod +			,
		append
		return this.a) :
			optioneach(functio"<a>" ).data( h the existing con
	$.fn.removeDatleft * mod	-								set.parent.left * mod	-Data.c			// The offsetParent's removeDaeturn removeData.cal
		ce = c[? "p ch
				( ( this.cssPo (this.op;
				proto.plugwhen zIndo.helper  ; inPERIODR
		ia ( $( el ).
		this.relative_conta, to be overridfn ) {
			x for widgets that aren't DOM-bf ( kes.bii= fah" ), 10 ) || 0d( elemeit );
		}

ent's offset wit
	retdocument &.call( argu	}ight ) - (  brois.scros.heighoptions.hebar.js, jquery.ui.sliddgets that aren't DOM-ate-foch.max( ce.scroly.ui.datepickurns 0 when zIndex is no
		this.relative_conto = this.options,
			scroll = this.cssPosition === "abthe prome = object.prototype.widgetFullName || name;
	$.fn[ name ] = function( options ) {
		var isMethodCall = typeof options === "stri
			returnValue = this;

		// allow multiple hashes to be passed .each(function() {
				$( this ).css( type, reduce( thisargs) ) :
			optionnt;
			realse,
		h ( o.contain
		this.relative_contlute" && !( this.scrollParent[ 0 ] !== document & $.contains( this.scrollParent[ 0 ], thisoffsetParent[ 0 ] ) ) ? this.offse, to be overridcrollParent,
			pageX = event.pagf ( "area" = event.pageY;

		//Came;
		if ( !element.h - thisons[ key ];
				}.ui[ m
				// WebKit alw - thisns auto if the element () : p://jreduce( this, ; inative_conryui.cleSele(function( _ment,e === 11 )			.att;
		 "fadeIn", hment is no l: 38
	}
);
		alse,
	if( $faultPrevent);
		fect ) {
	$.Wid$([]nimatetyle.posittainme}
	retustoppen.outerHeight
				}
		geX - this.offsestoppeleft: e	}
				if(eveAbrid: f	}
		va", f) {

		if (or( "n.heir:.pageY ?.pageYvent);
	 is, fullNsuppl jquery.ui.tooltip.js
* Copyriht 2013 jQue= everts.lataSpaee #5jqueeff		_c-toty$.et.clicoriginet.cli: {}
 was/*!
 * name ] Color Aototype s v2.1.2	//Chttps://github.com/jetury 0 erro-c gri
 *	//CCopyndle) 2013 name ] Fest(rt: jQ?
		ll modify tanceors	//CRehis. the
			c.cssMIT license.0 to pre:/ 0 erro.org/Math.roalid arD;
			Wed Jan 16 08:47:09rors i-0600
).fis
* Copyrig.tooltt 2013 jQuery FouesizabtepHook			}"lem g//Getr gridl cachocumenontainment[1lem;ontainment[1				/ontainment[1Topr gridg inv((toumnRuler gridout

$.r gridtextDecoble( e>= containEmphasisr gri") );
/his.sequals .ui.er;

+dgetN -dgetN
	r[1])) : top= /^([\-+])=\s*(\d+\.?\d*)/,.gridions s evRE elemh: thn  instn fa

	_c?
		t initial(top -tupddBack) * o.Pucto			$( 	option: /rgba?\(ath.ro{1,3})\s*, this.offset.click.left >= cont(?:ick.lef?(?:\.\d+)?t.cl)?\geX -ndex"scss("marginL execResultelement[ : -1,
	.ui.dd	 : ((left [ 1 }

		reft >= contain2ent[0]) ? left - o.gr3ent[0]) ? left - o.gr4 ]optiohis.of datay.ui.eft ? ((left - this.+ffset.click.%.click.lef							// The absolute mouse position
			ft - this.offset.click.left > containment[2]) ? left : ((left - this.offset.click.left >= containmen * 2.55t[0]) ? left - o.grid[nly for relative positione3 nodes: Relative offset from

		return {
			top: (
			// this.regex( elemes A-F._on( ele {
		dow ans.hconta ) {Speed: 20 l	eff = td]) * o.
				page#([a-f0-9]{2})on === "fixed" ? -this.scr containment[2]) ? left : ((left - this.offset.click.lefructor );t : ((left inmentgridme = !op	pageX -														id[0/ The absolute mouse position
				0]))/ Theis.offset.parent.top +												// The offsetParent's offset without borders (offset + border)
				( this.cssPosition === "fd" ? -thiset from eleollTop() : this.offset.scroll.top )
			),
			left: (
				pageX -																	 +																// The absolute mouse position
				thi without borderthis.offset.click.left -												// Cl without border Click offset (relative to the et ? (hsl																	// Thebsolute mouse position
				this.offset.click.top	-												// Click offset (relatis+ th ===slaADD: ainment[2]) ? left : ((left - this.offset.click.left >= containment[0]) ? left - o.grid[ /getNelative offset from ele	},

	// From now on bul

		return {
			top] o.grid.toolt.r gri(offs(top -=ent, ui) {
		erat]) ? leftg inv, green, blu) {
lpha							: -1,
	mentnt, ui) {
		.f, 10 seugin.call(this, type, [event,uppliecelHel		//Set (lef,
		LE	lper6,
		LE]);
dposition	idx delay || nt) : "byte"{
			element(this = this._conve	if ($tionTo("absolute");
		}
		type = this._conve2otype._trigger.call(this, {
			top:is.oRemo) {
			this.positionhvent, ui);
	},

rtPositionTo("ade(thisute");
		}
		satuble( en $.Widget.prototype._triggerpercble {
			elementle.g.nes.position
	},

	plugins: {},

 this.positiontantiation	elemlperTyppe === "dabsolupositioflo?
		ACT: 109	ma,

	55on() {		e", "connpositiois).d1("ui-dragion,
			 o = insod: 36
	// F	var inst =le", {
	st);
		};y ===ositt });
		in{ ) );
/return fat.but });
		.ui.sent });
	n;
	 this._uis );pt.fi[ 0ent[	$(o.st.soginabsolute posituseus= ui ||s(this,  = thialianstaofue;
	};
};
croler sften
	- thitable");
- th {
	/
				return(lefrtable).eimmedropoly
ction() {
	uctor tcssT
$.elick.top >= cng inv:(lef(1,ons(.5)";le.optio.				s=rtable).ns.revert
	k.top >= contai.iunctOf( "(lefod &> -1nce: sor jQundex: 1useup?
			eventlperrProese: starge			s?
		Remo f(type
able.eds to ( target ) eds toeOut"eds to						elHel.ion() = "_ accensure it;sed as wthis..ontainport: conve3r eleggable", "conn					}foptio};jqueryrs; Licenclampnt;
ype, lper, [elownt);
			}
	s, jqrn a vent, func[vent, argumss( "t was
ffecte wher=		co =t, ui]);
		//T(iSortable);|| !ctiondefortatill :nctiondeft.clic$(o.~~event) sh;
		"Botareno	delavar ce
		// musve numbDefau we are});

ed =oet.r~~e wher:tructocompoIf we arthis.//);

ui-stp	nextn et);
	) * o.grisf we aretParephetTi			hmanagui-sthialue )  = t
		//IfisNaNm: inst.et, ui]);
		//T sortable, but usableorta = t		}
					heladdt = t!set ||mo		.bittom" ],
s [ "eX) /neat tion(te", h(),
		r ] :vent) lperr cacly;
		0 -> 35verahe sortae wher+l = true;) %l = true;e, but alst.bunowt[ 0 er cachyl = touseStt =  = telememi #6950max ) {

var0 >f we arr, chil= trua /^uwe have et a temp:= documeht exist fro				pageX =( to sup;
			}
		}(event.st.sooptionortablethis._ortable documvert: "= to sup,
			effectNamhis.e up to			pageX = c (this will istructo										rethas d $(thisd[0]=the sto.re. : (revert: "v || posiupe ==this.i&&stance._ has tothis.iror( "nnsure itinstance._ initi.app the s.focusabl	this.is.cssPostableldRever[kept in st ]s been the" );
			}( c.cn thimove n dranal iuse
	_ssigute thmdle) crollSptwic	return oh well... ] = (restore prstore propertiwell asow" nal it: "auto", left: "auto" });
		//CachhouldRevert) {
				nal itt) {
	" );
			}exe;
$uldRevert;
				}

 )Helper the prot		.arotot handlerProurn this.eacme );
//see ti0
	},		pageX =  - this.
		TncelRemovallpersabledChverable.a
				if(icpertay: 0
	nal ite

		}), = fals"transtack: fatotypontainisoveras, "hroit's(t is sybe0)
			s) (Gecko;

		$.each(insas, ui)(0,able arginsnt, ui) se;
	.offsetable = { duratio
					in: func, ui), ev && .
		$.each(i)
		);
	his.documensp: +but alsuseun: f &&  ) {

varbs = i[evert: "]the dst.sorf thalow calling thest.sor WebKit ay.ui.inment[2]) ? leftraN( (this, type, [event, ui]);

			item== 2013 jQuery Fox
		// alw{
					this.mois.instance.contancelHelif ( this.lenvent.targ;

	.0 errofined dasOpt funhe origi

			var sorta

		ioned n(thiselementhis.inf(this.inst( "ui-state-ho			.atrototype );

	},nt, ui)et an () {
false,rtabletersectsWith(tdocument ength);
		1he elemenfunction() -= "oumeectingffset.click = inst.offt.click;his.in		$(this.instance._inles, f[ce.offset.click = inst.ofncelHe);

	},"typeyeate the pmoval = tar o = this.oce._intersmelCase( t has toto support re() {
//Ifble's n_
					v)
		);
	};
}
isSortable.in				$.he origie up to datesortaany ch (this will his[  = f- this.offgbaunctionidx				}"activar	} ecting) {
			if(inn arguments, {
				innermostInns(thisSortable.inobjtTimhe origilick;

		() {
			of.origin
		}

		e up to date (this will ensure it's initialiseffectName inted as well as]element[ ef top: "auto" });
				}/Now we fake the s.sMath{};
		evI - v1.1prior to initializastance.isOver) {

					this.instance.isOver = 1;
	start|| (/abfor the soruery UIe up to dating;
				});
			}


			if(innerm		if ( = $(thisce the sl: "inpuex;
		}?
		we krevehowngConst
			 of drag// or top: e the st&&e can ttoar inst browser event, e whernst., arrall( eleme && visib
		ip."+thinforma( c.css, vanst.o		if(dTo(this.instance.element eient[t).cloneition, value;
 truec[ 0  inte 0 ] != still over t", {
	version: "1.1e list gdragging  });
				}tItem = (Revert) {
		ts && $.efed browser this.olper: !!img = this.optioniSort.appe(/(autMether cache ie, fupe[ prop-staacti( "posilwaysASortable) of dragging e the ssecting) {
				//If it inte 0 ] event, uontris.easing,=== "numbent === "docum13 jQuerbop",		iftFull this.is.instance.currennt, ui)inon a r.appendhis.instance.by clon.mar3r = 0< 0element[ ef//- ther) {
mostIntofption			this.instance._ms.of	return let, we  event= typject( optioevert) {
				t = inst.oe modify a coupl) { return  the list group iteOver variable and set	elem7,
		$.ui.pluginithoup of the sohild 			if(				this.i.cancet: optit = $(ths;
					thihis.oe up to date (this will _nce.isOver = 1;lemen= th || (lay ||is || (/abi", left:ance.cancelHe(func
					
		}

		ortable", em, restore prke the st||ntItem = $rentItem = r = function()ack h methhe start event of the sortable _		if(innermostIhis.inst || (secting) {
		! helper option toffset.pnce.fromOutside = inst	if(ortable", secting) {
			});
	},
	return  step .offset.parent.left - this.insortablebject( optionsortabl		if elHelpes[ key ];
				}
			.8
ifis;
	},
	 - this.instang it to the sortable and using it as inst.current, we modifywe fake the start of d.8
is.elemore propertse $.widgety ] === undeortablarent()i-dr
		$.es, inform the m0)
			te);
	 {
				}
				ifemenet.parentll modnstance.options._hend.le
			options initithis.in", left: "autoor( "noartainmenerProponded ver somOpti.parent

		$.each(insn(/* evethis.instace.opstance.is that
					//hack so recei.elestance.inction());
				eft -//Preveby cloninganceve thenow we fake the stance._mouseDraing;
				});
			}


			if(innermost/invala.callcting) {alPosit			tV,
			uistanc[r("out"nt[0]) ende._uiHasut evinstance));

);

	},
	stop: function(event, ui) {

		}

		//  arra elemeition" )sh(thiAttr("s
			// o					this.e helper option h.max(
				Maons.helper = unct	rettainmck.lefttance._uiHaemove our currentI						instancs.inste._ui
				// Clone obje= false;
rue; //Don';

					//Now we -laceholder, >l = true; / 2}

				//Protance._uiHPareortable pls.effect[ effectis.instance-	//Now we e.placeholder) {
						this.instance-placeholder.remove +obj[1] ||elper back to it's"activa();
					if(this.instancl pasure it d+

					inst use a little  intersects with	if(tore propertiesns.revegins
		iblen,
		table, butpaqplugiDon't revent], [etNode.sc).optio-rsectingournts );
usable = $ctsWitance.o					 IE returns 0 				proto = $nstangreate;
		}
		tby cloniconta thargbe the ,
	_cra("u the helper.optionove the helt.positionAbeY = con.ma it gb (this will v, ir", o.cursor);
( 1ffseugin.ption
});
 + a * v {
			 drag stooRgbareturn	if(this.instance.curp
		EN&
		sSortval) {.helpersor", o._cur
		var o =);
		}
	}
});

$.ui.plugions = {}re helper Thii > 2false;
offs:tart: returnValunt, ui).css("cursor", o.curectinarent();	), o = $(this(eate the p			inst.c o = +		//Copy over+ "/ Caion(eveHsl ui) {
		var t = $(ui.helper), o = $(tRemo.data("d neeaggable").options;
ity);)t.css("opacity")) {
			o. has bre helper option tvinstity");
		}
	e = base;
		b propd[0]1so it2tart: funciemove <instaar i = $(th{

		//Get  v * = o.		if(%true);
			}
ions = {} argument"cursor"Remo.css("cursor", o.cuRemoevent, ui) {
		var o hsl(this).dta("ui-draggable")ag: fons;
		if(o._opacity) exui) {
		var t = $osition Aevent, ui])nction(th - 1; ir o = $(this).data(ges thaion(event, u"cursor"if(i.scrollParent[0tion(eve, an~~(rtables*data. Use $.lers ) {
			"# accsor", o._cursor(t.css("opacit$(that).cache sstIntto 0st.sorance.clper || poed wevt.scr norereturn(/ ThesOver variabvtName + ".preffse0 accv.css("op})lse;
		[ key ] 	llTop = 	if(this.instance0], this.ins}
		t.css("cursOptio
		$.each(insscroll.vent, ui) {
e dragjquernce.helpstancelperPropor the hel.fnnce: sd nee a newE: 4s adapoval= ty: o.scpreventc// agoogt
		om/p/maashaack/source/();
		/packages/graphics/trunk/src.scrollParbs = i/HUE2RGB.as?r=5021e._trigger(hue2= $( p, q, var in	is.opth +sor",%returParent * 6 <sor", o.c			inst.xtenqhis.l paed = stancscrolled =2i.scollParent[0].qllSpeed;
				} 3 <r) {
				ent[0].scrollLeft + o((2/3p+"phl paollSpee			inst.the dstInterag: fttop ement );
	tions.& i.so.opacity);0tore helperhelpity);set lSpeed;
				}
			se;
e still over the sortthis.instance.contaicity);
	}, be }	}
		}isOv - o.scrol/dataGHT: lTop() < set rollSensireat			if(!o.arollSensigName !=d[0])) : temparent[0drag all(,dgetNameeverop() - oiancellSpeed);
		difeventemp-reveDD: 1dns;
temp+ageY - $dman(doc* 0.Sensih, stanco.opa		} e=ument)& i.sc.ins-drafect[ effect.top -olled = $(docu( 60y) { gmove its ) - (		if36ment).scrollTop(g(document).scrollTop() + o.sb._cuSpeed);
				}
12ment).scrolscrollTop() + o.semov "vaed);
				}
24ment)this, ") {
a () - ent[ 0ger.curgreyscalthisich,lecte sintainme tion: thisocum%rtablll mowimorelSpeed);
		
	relper size
		ble( .cli$(document).scto };
	}

}) (adper)o.opa) - (erollL& i.scginament).scrollTop(lnd n0.rfloled = $() - (/) < ent).scrollLef).scrollLef( s wi(doclTop = !== "x") {

		//Get hLeftd({} s, l, a = t.css("ose;
inerC wast = scrolled= typi.scrollParend neescrollLeftt.offscrollSpeed;
			t.offs}

		} else {

t.offs!o.axis || o.axis !== "x") {
				if(event.pagt.offset()ument).scro.ins

	}
});

/nd({}, ugina.add("drallTop())ap", {
	sllTop($(t.offset(-draq

		/ft($(do? l+ o.s];

e
		:		// 
			s ||true;fset2 * ne aqL") fset.click.ent[0].tagNay) {
					i.scrol+| ":d/instanc.overfl-draar $t = $(this),
				$o = $t.	if(this !== i.element[0]) {
				i.snapEle-ffset();
			if(this !== a
urn  wasbe up to date (this will ensure it's initialiselper),opginaed independecall			//We can then fr elemtions.helpeP: 33er.pret = inst.oSortabl" ],hisSort)ag and ne/TricrollToptore propertiprepareOffse.isOver =inst = $t initialanstance
	};
}( funniti")) {ur own helper "cursor")ive/uif ( et.parent.lce._intersstance.optioop
	
		var o =rsectsWith	//If we are this.instance._inters) {
		var t					//by cloning tp: functioe = thitance.helperProportie where.data(ris.oflse;
					}
					rODO: so our move-in stu?;
			r :he elementll = f= thi {
		vngth - 1; i >= 0; iance._mouependently
					this.instance._triggervt.snaarr[
			t = inst.snapEl?rue);he sort) {
	instance.|| y1  helper option | y1 >inst.tersects, we the list ements[ i ].item 		}

			}
v{
		se a littlcity", o.opast.offset.cl		if (ss("curtance.inst.s Use $.wrenstance.optioinst.sOver variabs, thginal position
		thisss("curnap.relce.offsefirst,
			insted()&&
			() type$(th;
					hnapEltion: this() < o.scrol/Trif ( x2 < l - d || x1 > r + d || y2 < t  thiables, f - Position instance.o
			t.leftions.hei-draggabl 0 ] !// the new version posi- y1) <= d;
		inst.options,
			d = ot - d ||napElements[i].left;
			r = l + 	lperP.instance.options. Thi
		vard nee?erRemov che the con:nd it intersAbs
		}nst.snapElemefn ]nus the  childements[ i ].item lay ||_trigwas
			// oif(ts)  opti013 jQue{ durationsnapItemuk ) {
cel = (tn.top = inst._draggabl{ duration),
			ui	thisscroll	if (!busemion;
		if(ts) {
					ui.position.tohe list of exis we are still optitionst.sortionTo("relativ.instancrelative", {urn a stringance.element[	this.ins? this.origuseStope where z-iions.hethis.inbIndexNotNa			uime, th ui, { item:_trig {
	stty) {r }).lef}

		}= "+ false;
		osition.idden";

	if(inst.snapElements documever variable ap - inst.snapElementent)migh

		 thiddthisfsetso it.fx.s.of.scrollPart.bu- thiitionAhook.) <= ccepp", t.lefts		};visibvert: "ofner cache is2) <= .abs = Math.abs(r {
				});
			.abspElemabs(ng oth " ons.eae sort_conv				//Trigger ton.top = illow cal				bs s[op - is = e.get(t: event.pagesest([ 0 ] )gins.top;
	sortable
, { n;
	lay || k.top >= contain" ) !=st._converlativen() {
		$.each(insspaceents[i].left;
			r =, lefteft;
			= uuinal item,to support reisOver = 0 = 0;

	"relative", s("curnal ite			.ft = inst._c				thill the sortab&&				if(}
		t.css("		$(or", o.curgins. {
			v {
			
			k.top >= contai("reery.ll( argumen :e", {set.clicwhile.origin.revop: b - inst.help
								width }).left - inst.
		$.each(ine = valuen.left = inkey ft = iuctor k.top;
ject( optiotryect( optiotop: b - inst.helpergins.top;("relativetPositionTo("relati	});
	},
.left = instg && (tseHelperProportremove(f(i.sc( 	element[ eft
					thn ui.help
				}
				if(ption(ins.left;
				}
	&&ins.left;
				}
	 left: 0 }).top - portlet	(inst.options.sna		th"drag"ermostInons.easing, callba
				}
				if(rent[0].scrollTopents[i].iirst)) {
		", { ctor }
				if(bsf(o.snapMoapItem: nst.snapEleme			hr
		zdled 

		lick;

	 typeor returerr-soronh : ositi" been thlike '8,
	'(eve'inherit'- v1.10.3 - 201= thsor", o.abs(b }
				if(bsdraggable"fled = $(				thifx., { tnctitionTo("r.abs(				//nst.optixvent)top - in "absolx.ions;
		if (oh(functup).each(fseInt($(gretName, thiggablrgins.top;
				}
				if.().f) || 0;
		css("zInnative: this
			$(tth));nagelowOffse.abs(t terHeif(ts) {
	}

	offset.c", {rgins.top;
				.l cachst.helpe

		xpa"ui-draggable" inst._conve remos).dantItet was
	e sort[ "nd(", "gation, "(true =, " + ".;

			//Trigger the group[0])		if(t.c[n ) cach acc", o.+ ""relati			if(o.snapMore,
				//we		if(t.cTop = y1) <=Basic.origin(sort		eve(l - Ustionnt, n	var .currl modif_zIndex) {requioffs			.bi y t.css(helpe- Poshis.eventhis.ost.sortvg-(sort.js.tart: ortable");
			if (sort= $(th o.co1.;
		if(o._zInkeywords
	aqua: "#00ffff pagblaeturnce idgeze ) )vent$.widgsize )fuchsierencff", {
	veglse {"#80Pref	widgeturn nce 8get("uilimoppable0.3" pagmaro 46,
#ons:,
		acnavEventalseix: "dol );
	ntPref,
		acpurp	TABs: falix: "dAbs =.10.3",
		acsilrig.a"#c0nce: pagteal
	optionix: "dwhi
			.10.+ size )yeSort
		activ,
		arn ( x 2.3.ft: 0 }).top - ce ) && ( x <stop of		}
			}this.instance.contaiata(thiermostIn
		activateoptio})t.marginui) {
/*	this.isover = false;
		this.isout = true;

		this.accept = $.isFunction(acce/
		this.isover = false;
		this.i CLASS ANIMATIONS is.accept = $.isFunction(accept) ? accept : function(d) {
			resout = true;

		this.accept = $.isFunction(accept).js, jquery.uiinmen			ne elements!== 

		//[ "ada va{

		if(!p"re z-i	}
	
	helpe: fuurn th(bs) {
: funcrototypment[1] || toppables[o.scr grioppables[o.sclem;oppables[o.sc				/oppables[o.scTcludpables[o.sccleans[o.sco {
			s[o.sco
			.bi				sortp;
	ble.top: funclem;urn tposi
		$.ui.ddmable"));

	},

ocumenable"));

	},

Topable")}
		t.css("zInent;
					thi$;

		if (!if(in.length) { return; }

	tions.(functn() {nert: ions.absetAtt$.Widdraggabcursorrop[i] === thiss to allow calctor roup.lengthent, u			$(this).csi] === thisetName, thts[i].able._trigger(	ENDry.ui.urn thon.topop = inst.his[ leY - $ctor s.in (tsownerD, true, scrollSView		}

		this.accept = $.isFunction(va.g( thns[ s
		$.uon.top =till ovdd("d	thisument.burn tccept") {i.ddm {

		//Ift") {
renttor to ack( metoptionscrolfunction(ection(event				ls =lsitio
	_activate:= thlperPro= "af ( zIndex, val draggabmana[ i ].item.nt) {

options//Store s.left;
				}
				i.apply[his,amelectNaons.a)option.options.ace $.widget(			});
		};
	Opera,	ret<9nt).scrollLefParent, vaie to) {
; }

		min =ass(this.options.activeClass);
		}
		if(draggable 0 ] !==tivate", event, this.ui(lled	return fpplythe ddraggable toyleDifion( $,( ol
		$.uan owurn top = inst.) - (ev];
			useu[ 0 ] )ropoParentuseup(/The s._trigger(function(	},

	_o[e));
	ent, left:able){
	
		var d {
		 inst._convernst.marmanager
		$.uidmanager.
	start: func.options.scanager.e thstance. ui, { item: inst.eositionTo("r) - (!draggaboup = $.makeArlement.removeClass(thi) - the d		});
		};
	 o.acce<1.8
			thi$ = i);
	}
}s[this.opdraggable.ength) { retry.ui.if(!this.0], this.insaddment))) {
	= t.css("._interse

		Ove-inscrollPaddClass(th.filods ent))) {
		});
ent)eyCode:et.clict.bridget.js, = Math.abs(r - x1,help, consteaWe'r,nt( c.css(		});
			rentC.sis.e(
	_out: function(event) {

		nction() {

		vaqueuent. i = 0; i < parts..bridgein unmcated. ,
	_crasui(draggaent
		ifns.delay 		neay );
	"val) {s autt.js, 10 ) ent[0].l elements stionhasOptiont(trentItemmargin *.filt;
	}
});.topnt
		if			this.iapuse
	_nt
		if ove-inment <a>" ( size === unde.optiack({
			return;
		}{
			return;
o._cujs, jquery.ui.resizae.snadraggablenapMode !== tions ==t: rhis.instanc:: function(key, valated. se;
				}
le: functs aut				nexate-hover=== this.elementaggable.elemdule, opte sortetHeight };

		// Add		t.css("zI0) |ototype = newons.height[se.protostart of drggable.ed droppabt, ugable.ies in td droppable  mapName, img,
		| 0);=== this.element: functionm ||nce.ggable.element)))conta -/Cache theThe a.apply(?
		) - ass) {
				this.element.removeClass(this.options.hovemeFix:ons;
aggable));
		}

	},

	.el(eventlement to o) - (ev);
		}
		if(dragg to offlper
 {
			varthis._trigger				protoion(event,custocss( thisss browsrrentItem || draggablelet moi(dragelement[0]) {
			return false;
		}

		thisoffseuid sc caunt mayiner mi		ths) {
				this.element.removeClass(this.options.hoverClahoverInfive)/).t(functifin un.Dtion				}lay ||op
			}ble: func{}, oy.ui.eff/ novent		var metho				tleui.ie && (/(stati"draggdfd.resolve.elent.offseing the list group._interseelt.bridge( 		if(
			,
	},		this._triggern) {
				$.[0].parle: funct of 0
			return Add eleme	});
		ieHelp$.totyns autoght ment.removeCla/ Ign ).s ) his.element[0].;
			})ength )fraggable.optiment)[0] === this.elemenns like - thiggable.elplacehols = $.wislic, {
		is.or cache iance
	wt[0],nt
		ifer.current,
p;
			b = (childrenIntersectrClass);
all(thi= thisurrent,
0],(draggabem = $( thisionTo("reele ]( ohis[ "nst.elemeroup ite sortable
0]; };

guarnt;

		//Propnt[0]ifry);again		this.e $.uidrop: othe alsog: func{
		; reer" + na

$.e.ui(m.css({ger );
		i(ls) {
 element
	pable");
ore,
s(t -ment.fnrenInters
$(dogable: s
* CopyriggeX 			if(this.optt.left;
			"fadeOuthis.is.eunction(event) {

		var t of the sis.e		}

	}r", event, this.ui(dra(ls) {
					rsecti{})(j:,
		x1 = (d }raggable.positionAbs || dragdd("drgeX nt.remov if the elemente", "zIndex"ementdragg.call(i] = vaffect.js,se;
	}

	var draggableLeft, draggableTop,
		x1 = (draggable.positionAbs || draggable.positie elementtivate: >crollute).left, x2 = x1 + draggable.helperProportio		};
	})dth,
		y1 = (draggable.positionAbs || draggable.position.absolute).top, y2 = y1 + draggable.ons.height,oportire z-index se;
	}

	var draggableLeft, draggableTop,
		x1 = (dra= falraggable.positionAbs || draggable= false;
ablet[ 0 vertPoooleelay ) {f
				t <this.instance._inail if dn.absack)).sort(fu
		distan.abs= Mam			revent, handlee.position.absolute).top, y2 = y1n as the prefix,) {
			hleft, x2 = x1 + draggable.helperProport	(ble.he? ions.width,
		y1 =  urn 		case "fit":
			retme = !optggable.positionAbs || draet.bridge = DelayMet = true				y2 -ble.hegable.helperPr:
			draggableLeft = ((draggable.positionAbs ||- th z-i "fit":
			returble.helperProportionrsected befo+ draggable.re z-index oportiseProterPropois.instance.ffec,})(jraggable.positionAbs || d		if(this.optaggableLeft = ((draggable.positiontersecns.wiverAable.p};
	})>= t &	top: draggable.offset.click).left = scrept  y1) 	this.isover = false;
		this.isout = true;

		this.accept = $.isFunction(accept) ? accept : function(d) {
			re			retEFFECT);
		};

		//Store the droppableable's proportions
		this.proportions = { width: this.element[0].offsetWidth, height: tthis.element[0].ble: funcportions.w
		DELETE: 46,
		DOWN: 4i(draSaion(is.originr("drop", evert value<a>"ahovesa);
	i.position.top	} elsegroup[0].helt;
	},&& !( th 0,
	s ] !== do	var draggablsl(inty",		$(elper option query.uiy.ui.nt[3] + thi+ function,erClass)Mode).optionsfunction(ble");
	ble", {
	si(draRe<a>" fsets of drage i isly i.ddal = falp", eay: 0
	ppables
*/
$.urtype :manager = {
	current: null,
	dr d || y,hat.droppab { "default": [] },
	prepareOffsets: function(t, event) {

		va| y1 >r i, j,
			m = $.ui.ddmanager.drop.left);
his.accept.call(thi1.6.rent[0o.axis ://bugs = truey") {t;"><t/9917[i].elemccept.call(mion(ement[lscro-1,
srProportionetParnyrn fay				if([i].elemW{

va.hei
		if(dtropo breturn;""(ui-d0Helpeable-we ss( eet.cli[i].elemst.sortables
		this {
		) -  "wi = thiy2) <=common				if(.css({raggable elperProportions.heightand non) !== "hiddenffectName ]( oger.droppa
			se $.widget($.isFunctModnt).find(":data,sort		var drleft		//Aions =ions tionTo("e drop el.*/
(":, optionsop: Plaiet ===ider i = $(this).da		//upplied
is.o		$. visfset[s thcies]> b aeven !(/(dragvar thelper;per: c.hs );
	t = 0;littl in aluelex|| isontainmfuitioight	if ( r	}

		}) &m" )h
	getB m[i].ei-draggable");

//.hels( this		}
				ify, x

		seProtle =
	},
Mode) {t ) {
		// ) //T: s) {0;  e.g., drad = famiddl ui)	// C.5reate a copy of th-draggaables 1reate a copy= this.ops) {nt) {

		var/}

	},
	d

		retmostInteable, event) {

	set  dropped = fa "foo: lTopCreate a copy of this.per) {

			n case the list chanspace  {

		e drop (#9116)
		$.eaclToppe] || []).sr.droppable$[ na i = $(this).dagablex: this.iy: ;
		supplied
itemraplper: pageY - a//Get", "nctio,
	dragcop", eent[0] &&er cache is	Proporrop._mouseCapture:& $.effect inst = $(this(this, everentNode.scunctiond (Geckoent).d					/uery.uiainment.cdraggry.uet.clickopped;
" rties (sck );
		} else
				thistarget, thisoppement, "tabindex
			}
		});
ersectt( handgger( event, data )ctionPropor
		this.ment[0], [ event ].con( draggab.ui = $ent ) {
		if ( !ui = $_drop: 	(y1 opped;
	d( selediv></ thees scro== "number".offset.click	this.isoves can insersectifont4,
		P"100%val) {	(inst.optiochec		$.each(inody" ).ber.drop	if ( Proport	(o.addC
	// Fros && thisverable:;
			}
	S		if(thisndefii{
		 thiunctiaultViehis.13 jQuerin % - Fixes #524a("useup rn dropped;

	},
	dragSt$[ nameraggable, event ) {
		ing ui-dscrolling cus()tione, true, ht try$( elemen) {
		);
		};
	Firefo//Thableme you
			}

			// exponstaanonyom
*tClicoportionsxis || obugzilla.moddmanarigiPlai_bug.cgi?id=561664// Irst)) {
ht try.inst.poray($(o.stack)).sht try this optionfals (this.opvent) {

r_curopped;
	le: funct);
	},
7595 - nction( (evs = ucu( event nctiont - thisables[t.optiooppabht try s.ofe(event)) ables[t.optio
		// ver = 0;

	ons  this.gr.les[dvate.call(tthe positi		this._deactivat //Hoto = t.buccept.cal4
		thissoble.stom |iable.onappe= deeighs.helperoppab|select,
	runi.contaiunction(turn (/f move .dropf		this._crehis._("drop", evnce),
				c lperscope] || []ragging	innerWid .offsetng
				slice(),opped;
ble.elstyle.posithis.elementroup it "isover" : is.options.greedy) {
				// faggableTop =			return ng;
			nal eves.offset. "isover" : null);
			if(Propor(functio "isover" : nuz-("outes scrolleft);t.addClasscroll tion());

	"zIndex"eof key
		t.css("zIr thrm the ma	this.[	}); it's "isover" : nggablnst._conver| (draggableX -		 (parent.lend =  || draggable. (parent.length08,
		 mapName, img,
		nnd droppable 		parent = thisevent.pagement ) {
		
	// Fr0 );
	},itionA.ddman08,
		NUMPtione] = $08,
		if ( !handle				}
			}

		eup 3-05-03
* htt}

			if (th	}
		}))sPlainObje ] = vaffechis.visible && this.accept.calle elemet try this option. It renders positelement))) {
				this.isout = true;
				this.isov.zIndex);	this._deactiva.re		pags.can& $.effects veClass.each($.ui.ddmanager.droppables[draggable.options.t" ? "isover"
});

$.on() {

			if(this.options.disabled || this.greedyChilld || !this.visible) {
	p : scroll.ck );
		} else {
ble) {
	l(m[i the sortable, buplaceholdnt =, fa) {
 = inst._conve
				}
				if, ui) {
	ui: functagStop:	//Trigger trn; }

		.curr, min ) {
					paUs,"oody" i ].item. "scMode) urOpins.top;
				[ ementsreOffsets *p: func +does no1vent, this.uy ] === undef(o.snap >= t &//|| draggan et.cli		optionsove-in= ui.ofe== "numgable.hels:._trigger(_ {
			thiA element(		if( !able.eturn ggable.ent) {

		var - thi.inst.eact)) &ll		optionsemenopy the ggable.helpe elemenisPlainass(ths( dragg"isover" 	options[ et.clitance || 0;umber(va", evenstance.pos a new rototye.optio
			if(ocss( 		if(o.g {
	reti].item tem: in draggabr = th...itivity)	options[ event) {

			options[ solutlled = $n: "1.10.3",
	ft, l, dr{
	return pe-focus" x: "resizisover" t( c.css(=x: "resiraggan.abs
		event.p{
		alsoResize: false,
		animate: falggable.?imateDurnt) {

	 "resize"			iaggabragga.optiois.es[ false,
	.slice(): "swing",
	helptRatio: fals		aspectRat{
		alsoResize: false,
		animate: falle, eventse,
		animateDuration: "slow",&& // BomaxHeight: null,
		maxWidth: null,	event.lled =  d;
false,
	to.mouse,tPrefix: "resiz dropp			return  draggable, eve

		if(sed : fals;
		thffseified
		_out: five" !isNa("ui-reshildrfx
		ive to e happethis
		thihandles: "e,?o: !!(oaspe: !!(oiopor,
		helpe: "o",
		helper:: !!(o]pectR	originalElnermostIns.sho !isNae, droppdex"( c.css(nt.addClasse, droppnction() {
N(parseIe draggable toandard elements		var ",
		aniname,
// Vsiti || "ui-rment: s (ype.on(evles: ", = Math: !!(imateDur!: null
h;
			td: false,		handles: "e,s,se",
		helper: false
		maxHeidlerProxy() {
lled = Index")rtables, - t($.ud ) " {
			"odes
	Removal = teName.match(/caeft;
			ionsaggableLefyResizlect|button|img/i)) {

			//Create a wd.isents:],
			_hmateDuration: "slow",
		anir = 0;

				inst		//Create a w		var  elemh (appectio	thiturn o.animafalse,
		grid: false, our move-in suniqureturn) || 0;
}mg/i)) {

			//Create a wD0,
		sthis.i thep the eleAPIClass(thip://jquCode:ffset) {
		re		if(o.gntsUntil( /*this,
			o = thint );
		}
	}
};

}*/rop: functiarg
		}nager.prepareOffsetsition.absolute).top, y2  $(thi	this..datrue;t[0]) ; ret
			);

; retInstayResizMeth= thielement.wrap(
					);

 {
	retdocume element
	, {
e th

			//Move mar fullNa ] )		therance),


	},
	drmMove m(e.g., sPlainO)sitiLowerCase()ty) {
		//ActivatMode !== "outerargin]		retss("ui-resthe eelper || o	if(!m[is the prefix0], this.inse sortchildrenIntersectileft:"), marginBottotersectio"), marginBot(ls) {
						}

		if(this.acce		eventName =s; Licenrun(= funcns.hoverClassreturn aggable || (		});
		i
			);

e, dropp) {
			e")
			);

			tbind( es; LiceneClasare same elemenion: "slow",Element.csselay;
		if 0, marginTop:rea of opStarted =
				(t.css("resize", ent Safss({ marg

$.o = this.datawe need tions: {
		cancetNode.sclemenment )ent[ hoverCconsi,ss("marginLe	helper: ent ).pp: thiselementons.dethistraandlerof "old10.3",

hash oInstance && c  draggables
			if(te droppabloused :: this.origpe === {

		var i,lElement.ion;
		}on: 5up item, appending 
			//Move al element to ht"), this.ersected befo05-03
* httlementI		if ( !tfalse,
e sort	// n(/* evennd droplementemenfveDa).lenarentInsth
		d;
	}

	var draggableLeft, draggableTop,: null
		}); the place"ui-resizable-helper" : null
		Right: this.oris.height / 2) < b ); // Top Half
	is.originalElnt().data(
				"ui-resizable", this.element.data("ui-re= this	);

			thildpe ==,

	uniqueId:his.elResizeEs) {
					u.dataturn isOverAxis( dragga ".uoportiousei-resizable-e", s: ".ui-resizable-s", w: ".ui-resizable-w", se: ".ui-resizable-se", sw: ".ui-resizable-sw", ne: ".ui-resizable-ne", nw: ".ui-resizable-nw" });
		if(this.handles.constructor === String) {

			if ( this.handles === "ousedown"			this.handles = "n,e,s,w,se,sw,ne,nw";
			}

			n = this.ha	if (ggable.helpi-resizable-e", s: ".ui-resizable-s", w: ".ui-resizable-w", se: ".ui-resizable-se", sw: ".ui-r[0].nodeName.match(/cay1 + (draesizable-sw", ne: ".ui-resizable-ne", nw: ".ui-resizable-nw" });
		if(this.handles.constructor === String) {

			if ( this.handles === "e if ussee #7960
				axis.css({ zIndex: o.zIndex });

				//TODO : What'e if uing onns)  falsee;
	};
};
	ble" );elem = $( this		}
				ift") {
		ager && _trigge || posif ( instanurrent,
[ "r ===oper,L") this		}
		t.css("zIndex "scrsizable-w", seor tto refres], thisone final time  the  ui, { item:) {
		v[i], thents when overflow was cau(y2 >= t && y2 <= b) ||	// Bottom edge touching
				(y1 < t && y2 > b)		// Surrounded vertically
			) && (
				(x1 >= l && x1 <= r) ||	/ASING edge touching
				(x2 >= l && x2 <<= r) ||	// Right edge touching
				(x1 < l && x2 > r)		// Surrounded horizontally
			);
		default:
;
		lper siz;
			re) : .activurn (Rob));
Perotot(ent[0],www.raddinptype y") {ction()].offsaxisEtion(y(this, atring) {
		QuaerencCubi} el"Top/ If "Quie",  "Expo	}
		t.css("zIndexuseupss({ ",
						/nent)[0] ===ntsUntil( innermosElementnbindpow			i.i				ble-n"},

	_s			return ",
						/n
				S0].offsetHeig	ui.p: this.elemenraggnbindcotrue *for? TPIder) {_opacitCirc

				//TODO: What's that good for? Tsqrt"draggs noa littllinEw" )ied left
			O: What's that gop				scr||derAxisrollpraggab-dPos, padW2, 8y) {alcuisab not anys$(wi(s(this.el* 8mana7(docunot anything15to be exe	}
}}
		};

		//TODO: make rendercontst.m3		//M-);

				.eleouhis;
				//TODO: What's 		retuw	plugibs._ha = 4			//this.optis.ohis.) {
 else if._render-- (!thatelem-ui.po/ 1or",  $(this).daet()Name) {
		4.ins- is = this".ui-625sizable- padWhis.claOffsis na).scs wip,);

				this._pro up tallyResize();ntsUntil( nt, thhis.Il
		});] ? ion(
			 we wod :
		o = inthe el;to auto hide the O(thients
		if (		};

		//TODO: make rendraggthe el {
					igger("o auto hide the elandles.hide();
			$(this.element)
				.adis.op.itern inn("ui-r/Mat	that.araggabdClass("ui-r/Mat-2er);

).sc					this._& y2 <=.ui.tooltip.js
* Copyright 2013 jQuery Fou.currifalstInsousele
	soResithe maw.disabled);

f (o.disa&
			callb		}
					i
			.bind(at.resizing) {
					) {
		$(	if (o.disa.helpere
		thisat.resizing) ction() {
		at._handles - se						ret&
			callbitialize t{
						$(thiinteraction
		thiesizable-aitialize t
						that._handfunction() {

			}
				});
		}
all") {de: {
		BA.off.accordrWidth	DELETE: 46,
		DOWN: 40OME: 36,
		LEFthis.ld
			i.ui(dra.griAD_DECllap || iurn false; {
			:raggickgreedhea) {
		> lis).:
				-hasOp,> :},
	li):{
		ble").r_destroy: c === "isovihat positiozable-HremoveDaui-dle"-triangle-1-s
		}
	}};

		//TODO: Unwrap at samlute") {
					$( elem ).focus();
						if ( fn ) {!== "malParent;
		iif (($.ui.ie && (/(stati	}
				options[ key ] = value;
	options.hSdlesld.protorevH.pageY$};
		}) :
	st(this.css("posi//TOD) {
				$on")wser
		ui}
		},
-portses scro// ARIAfixedons );
ig[ positabnt ="px" );
.cssby th;

(furesizing")
					.rble coable-diemove(/s, fullN(this.elemenle =sizing")
 inst}
	};
	//t try t		if ( !tnt.addClasstyle);
		_crea-resizablis.originalElemei-dragf ( "area"_protancPanelle) {
	ns) if ( r			this.nstance.carefix: "resiinalEleme.offset.clfor (i in this.Parent =ositiot[ 0 ] !=ve_container widgetstInstance..optdeNameD: 35urn pageY - i.overflowOffsetk.top <emoveDions.hthis.containn, evif ( e/Run thll)/).tes$ 500,
		sc/Run thpropor	},
	_sePosithis.options.disabled && capture;
	},

	_mouseStopped = thisent.isDIle").rpable are same elemdle")ld widget using dle")raggable tion()edyChild |"<{
		pables can be recalculat) {
				$- = truDO: U wraet/17les.dle")dles[i]	draggabto callelec.handles[i])le");
			if(/Run thhasOptionut = tv.jquery.com/ticket/1		draggab.effect.js, /absolute/).test( el.: wrapper.absolu();
			};

	le");
			if(les[i])[ for http://dev.jquery.cdle")nst.ele0] + this-x"));
iniPos = this.element.ition: "absofixed/).test(this: iniPos.top, left: inifixed: "absolute", top: el.css("top"), left: el.css("lef.target || -x"));
			}).eq(0);
		}
fset.pnctio;
		retu			thn up m		th, "visibility" turn (/fixed/).test(this.outerHeight(),
				top: wrapper.css("top"),
		ss("lef thicss("lefptions
		$op += $(oleft = num		curleft = num(this.helper.css("left"));
		te/).te, top: el.css("top")tyle);
wrapper.css("toion consistcrollSeui-cornheigllion consisght: el.ouconsistent acrodth(), heirameFelper.offset();
		this.poslper.offset();
		tend(ry.ui.effeth(), height: el.outerHeitop =olop = num(offset();
		ttabength th(), h
// selectors
function f ( /^, top: el.cs/$.ui.puseStarll,
		starnt.top,
		fset();
ance[(tionsntItem || draggable ).scrol}

		this.: functionop += $(o() || 0 urn ! num() || 0; size
		ths[i])[
			o = table.e;
			3",

tItem  ? { width: el.outerWidth(), height: el.outerHei		if(t.c: { width: el.width(), hei, options(), height: el.outerHeilabest.sb		re| 0;
		}

		//Store neterHeight() } :  {
		Bight:sitidth(), heis.offse, top: el.csss.axis).cssbody").css("cursoht() };
		this.originalSisition = { left: curleft, top: curtop };
		this.sizeDiff = { width: el.outerWidth() - el.width(), height: el.outerHeight(usable = $();

		ifind(".ui-r {
			() || 0handles o() || 0;ragging= elemetItem || c0] + this{
			var elem = $( trrentI inst._convehis.instance.opthis.handles o// _cus();
	()nce.isOif ( rIndex") been th?
		helperPro		// the o._intersecTop = thi: l }).left -new version of ition, value;
{
			 stuff gets fielement
		// {
				413)
if ( $( "of element top: eer's cssmp.left)||0,
	" ).data( "a-b"ops upD: 35ssize.width,
	ure: functisu&&
	is.originalMopositions!instan
			wrapper.remove(lperPre", thied; ollSpunction neElement.c, value;
e", this.orerHeig l }).e();
		// the ortyle);
		_destroys.options.hel = this.si0tersecting = fal, value;
eft: inice._intersec.height() };
		t draggable an
			dy = (events,
			iniPo				parentInst		pre#533s wiry 1.6.1l: "inpucascaf ( 
				uterWidtest( thi o.a});
}ionsist[js;

		// d;
/ levent across br || 0;
			) {
		?
		on = { leition, value;
			while ( elem.length: "absolutetion") ) ) {
	vent.pa	draggabre z-index if posi + "-resize" :value where z-in] + thiskeyt", manager = {
	||0,
			dy ndation amaxthe acxus: 15
$.Wt" ? "i	if (altKdefineition.ctrlt + // the new version of f(key =Cs === ptionth) {
	ll = faate( ev.handles[i])[0];
			},
	_
		}

	ta.call( this,s[i])[("outosition.dler ] 	//AdjusFes[dr.mouse.js, jable, eveition.th) {
		 dropped = fth) {
	.RIGHTdd("dthis._helper DOWNdd("dr.size.heigresize", eve[bs(b.size.heightrollLeft.size.whis.offate a copy of t_helper LEFhis._proportionallyUPzeElements.length) {
			this._proportionallyRlass+;
		}

	e();
		}

		// Call the user callback if SPACEis._proportionallyENTERzeEleme$( o.ition;
				rion.left  DOM-based
		wid callback if HOM	},

	ments.length) {
			this._0		// Call the user callback if ENDffsetw, s, left, top,
			o = t ui-state-		// Call the useet it once,size.heedyChild |props.height =pper.cssiginalPos,
			first ize
pr.lengt(pr[0].nodeName);
	zing
		ments.letrue;
				par see if {
			DcrollSnment[0] + this dx, KeyDown)+$.s.position.left !== ps.position.th) {
			=	props.width =.UPandl";
		}
		if (this.siz/textareaoportioTi).test(h" ), 1isible) {
			y ] = value;
			} else {css({
				position: wrapper.css("positionion(event) {
		varon't re), 1r.apply((eveno, dx, dy]);

			for (i in this.		_destroyeach("resize", this.oriop - ecauseft: .handles[i])[0];
			return this;
	},

	_mousi.datepicker{ positiouterWidth( - x1his.gemove(!!imgtotype", this.oriragg: inst.ect[ effectnce the user can start pressing shift while resizing
		Positio
		}

	 appentend(s dx, helpeon

			that.helper				this.eltivate: funs._superAppls, jquery.ui.dled ||				this.el
		var dis,
			prele( im.con $.expper , evellParen (event.pageX-nalPosition.topop - thisevHeight;
			}y.ui. + "-resize" :.constru
			dy = ull;

			if (!o.animate) {

				this.element.css(($.extendrginorkaroun, dx, dy]	// Clone objects
	 while resi() - o.scr060
		zInd user calassNa_change[a]ze.width);

			this._helper turn;
lper 		prndex is ignoredable instathis._h("out").da({ posurn this;
	},

	_mous== prevHeight) {
							this.ele)
			)
		};

	}l.height() };
		thisdle === event.target || on(event) {
	adjustOffsetFromHelper(o) {
		ent = this.paremarginon(event) {

		/).test( ex for http://dev.jquery.com/ticl.outerHeight() } : { width: el.width(), heightpositionageX, top: event.pageY } for http://dev.jquery.cs.axis).css(".ui-resizable-" + this.axis).css("cursor");
	ght) || ss);
		"ind("", top: el.css "auto" ? this)ons = "px" );
		allow widgetss = this.element.posimaxoptions) {
			handAt: false,
		grid: fal//Increasel,
		minH
		//Increascontainmt.currentTa "_over" : "_outu might{
				$Ise this tb.minWidth) {
//dev.jquery.c" +value.apply ]( options );
eighty(th++uiype sted oneidth) ? o.maxW_;
		!== "aggaHeight, pMaxHeAspectRatio) {
			// We want to creaht() };
		this.orght: el.ou this._helper ? { width:	//Store neon is the requee;

	},

	_upFirst, compute the "projected" size for eacht < b.ght) || PlainObj top: curtop };
		thper.css("left")
		sition = { left: curle")) {
			o.nst._ forceesize
			this.orie = truh) {
a.leftminHeight) {
ns.refresper p)) {
			ouseStart:  = datah) {
 dataminHeight) {
nst._conver!r(data.toss({ margr(data.top)b.minWidth) ", zh;
			}( (/ar.remov) {
			this.positent.ta.heighr proportionallyR!r(data.h ");
		}
		data.heier(data.width)) r cpohis.size.widtght)) {
			this.				 data )= this.data() {
			this.po, height: el.his;

		if (isNumbght)) {
			thriginalSize.heig	}
	},

	_updateR}t) || ent.pageY 
		if (isNumber(data;

		requested one
			// th)) {off = { wmaxWidth) {
		);
		// weterHeight() } :: "p://jgreedy cginalPo;
		ta.width)) {
			data.height ersecti === "number") e.width - data.w") {
	, optiooll.dulute");
draggabpx" );
	ment[0]).minWidthtthis._mcheHelcreat Mathontainab  funclement.cshis._proportionallyagate("resize", eveneqsizin$.ui.hasScroll(pr[0], "lef 1 ),
		inputIndeBoundara.left = cpos.left + (csize.wata.h data.width);
		verabtop = null;
		}
		if (a === "nw") {
			data.top =xWidth && ((csize.height - didth -| c.elementsNumber(o.mftKey) {
			datlse;
		}

(!trigger) {rigger = this._chollParentatio;
			pMaFunctie reqo = inst.is.elemenet( chiing ui-de) {
				t "_over" || 		/naggaef || iitio
// selectors
functionextarea resize
			this.orirent = throll.drr" : null);
			if(roportions.hthis.positCache the marg pro || ($.isFunction(thn( event, handl= this.data(&& o.minHe-			this.nt();

		///Because the,
			hel( data ) {

		// selectors
function o.minWidth;
ement.dela	if (isminh) {
			data.height = o.minHeight;
{
			data.he= !$.isEmptyObject( optement.delaing ui-pMaxWidth, pMi o.minWidthrsectioement.delaent) {
				ret+= o.maxWidtrototype  && eleme);
			da;

		/ = this._th: 				$(st._uiHash(h), isminh = isNumber(
				$( tht) && o.minHeig-draggeight = o.maxHeight;
		}

		if (isminw && cw) {
&& o.minHeig() - o.scr
			pMaxWi= o.maxWidtr data,
			el = thiidth;
		}
	f (isminh && cft = dw && o.minHement[0] + thiscus();
			false;

		ifunct
			}

			this[c] =
			}
			if(pMaxWiata.top this.octs && treturgable,ries: his.met).clothis._helpereDiff.wityle);
		_d("cursor", "auto");data(event.target, this;
	},

	_pn.left))r
		mment.f]();eds t;
		}
oportioeachtend(sdata.wesizable-= thi {

			i("cursor", "autoth) && o.mi
		this.resizs.eventName:th);

		ui-id-" + (per.he.length; i++)/ ? 0 : that.s: $.nounctif (!dr	this			if(pMaxheight && !dnt))) {
			if(this.optitRatio:lass) {
		spectRatio),
( data ) {

		varers = [pr this.target || nWidth && (= ista ? 0 : that.sizeDirClasurn (/n dropp
		if (th"}
		if (rappeg: functin.left !== pString) {ition.ng others,rentsUntil( "ta.tnt[ 0 ]
			element[ "borde[ingBottom")ow" )portionallyRerAxis 
			isminw = isNu.pageY-smp.top)||nt);

		if (this.position.t}
		this._v_ositY-smp.top)||0,"border borders[ j ], 10 ) || 0 ) 
		if (i {lbac)];
				
			soffsetw				// ftive",  = t, jqtion") ) ) {
			el.c
			}
	es[dheight() - this.borderD	if (!
		this.resirderBottomWidth"), prel.css(= b.maxHeight * this.aspectleft) {
			dat
		}

		ret 0;"></d = o.ht: (that.helper.heigenderProxy: Isf(pMax		}

;"></ "isout") {
				.data(td regsizingance.ement, o = this.op"), 10) + (that.posi//Adjus				widoffset();

&& captutions;

		if (isNumtoer.oute},

	_mouseStart: ? 0 : pace +per.remld	};

		/
		}

		retcss(t) {
oneder.oInstannew	};

		/$("<div style='overflow:hi() - 1,
	nt.outerW				se;
			moveData*/ ? 0 : that.sizeDeDiff.we");
		ifddings, nt = this.hel		if (ctioe", this.orns.reveif(this._helper) {
ss("resize", this.ori
			[i].elemper );
 ; innt.ou== "map" ns.reve, to be overri ns,
			 this.elve positioss(this._hent[0]) {
	");

		thinew version of this;
	},

	_mous$("<div style = this._setConHeight) {
			 0;"></diginalPosi;
		nt, ann;
	o ._e if u() "not = f mouse
	m) {

		var h(),
		i this )fset {

	odw apgata, e 8ht - th672 this				this.elemeement, o = this.le='overflow:hi.progressba: func {
			this.hpositionsble, e
		x1 this.or(), hevar cs =turn funcrkaround fnt = this.hel "stach( [ " draggablependToBoundaris.size = this._helper ? { width:}
			if(pMaxHeight < b.- o.minelper.height(is.resizing =osition: "absolute", top: el.css("top"), left: el.css("left") });ght: this.ori)) {
			el.css(es can be recalc.extend(this._| forceArsecting = fal!ement, o = this.tion( ele;"></ el.css("left") });Height;
			}
		}
bugfix for http://dev.jquery.com/tick
			if(pMaxHeight < b.maxHeight) {
				this._aspeght: this.originalSiz functio(this.helper.css("top"));

		if (o.containmen) {
			return $.extend(this._| forceAspecis, arguments), this._chang) {
			el.css({ posn ui.hfunction(even{
			data.hei

	_updateCache: function(data) {
		thisp + "px";
		}fset || ista ? 0 :valuecore.js, jqlper || t[3].1,
				p</div>");

			ition"),
				isabled &&ition"),
				w:	},

	ement.ouurn datafsetWidt			target[aggaper inner" + narototype ts in t, o._z				.appendTo, event) {
		$t);

		if rapper.ou)offsptioCT: /Because thition"),
				widttion:;
				}
		apper.outerWidths.focusable = $();

		i.ui(draressing shift welement[0tion:er |idth( [eventnts, 1 ),
		inputs.siz"px" );
			helper sPlainObject		},
		n: fuerflow: , [event
			)
		};origina.left = c") {
			data.top = cpos.top +csize.height - data.heigheight:originh" ), 1pectRatio);
dialog.js, ata.heihat.size.event.;
	}eProtoveClass(sunct.widgetNao || ata.wi= typeof 
		return da("ui-resizabis, 	delay: 0n.left)) |"staticoptions,
		function(= that._proportionallyResizeElements,
		offset();
!btnIsLkeevent);offset();

data.wid;
		}

		return data;
	originaivate: funtop: fuSize: function(op: function( event odeName);
			soffseate) {
				tht,
			soffsetagate("resize", evenss);
		childrenIntersect) {
			haent.delay( optiiginalPosit				scata.width)) 	style = { width: (that.siz;

/*
ion: "ab= isNumber(data.wight) && o.maxHeight && o.maxHeight < data.height)idth))  ) || 	}
		if (a === "nw") {
	dth) && o.maxWidth && ((o.maxWidth < da];

			if (!d ui-resiista ? 0 :size: this.size,
			or, dx, dy]))tck()ction(ev	_out: fupicker.j				this.ins "bord
					ioffswnherit,
			soffsetw ns.reve! ista ? 0 : th= uui,
				s) {
		nce.Diff.wiwidth: p) = l + i	positid widget using t	positi ui) idth = b.maxHeieEasi&&toleranc.eEasilly bridge= el.ofnalResizetarget ) {
	var input =riginalPosition
		};
	}

}	focusaew current interna		this.les: "e,lements
		$.extend		aspectRatIntersect	grid: false,
		handeft;
				}
				;
			reth) {
						$(pr[s lirent(ble.riting{

		var nrototype tareOffse draarion -eEasihat wil}

	a.height ;
			rent.addClassateCache(dght"), 1uto hi		th
		$.extend
		$.extent.addClass("ui-res						top: s("ui-resiz data;
	},ista ? 0 : that.sizeElementsrigination,
									ret

	_out: function(everginBottom: tIntersect!offsetw), height: (thunction() * Resbridge( f (o.disa, ch, cw, width, height,
			that = $ elemtt.snapriginalPositevent );
		event.tthat.options,
			el = that.(pr && pr.len:tion: o.animatuto hi:				dura.get()eludes: jquer rev"ui-roup[0]).cssrevearent[0].tagNarevesected before,
	tion.left))px" );eft)) ar element, p, co, inment[(0) : oc;

		if (!ce)  {
			return;
		}

			});
		if( = this.origin	that.containerElement = $(ce);


		if (/document/.test(oc) || olength; i+if(intParen elemecss({ margincss( e+=ui-dn			ele.effect[ effecttion( method
		//Increase performance, avoid rument), left: 0, top: 0, = (oc- that.opvent );
		eve}

		ss( e	});
	},
asing: o.auery UI - v1.10.3 -target || riginalPositioapply(this, [event, dx, dy]))er.oute[event, this.ui()]= num(num(this.helper.css("left"));
		on(data) {
		this.offsn.top - thatHeight = pMaxHeight;
			helper ? {.extend(this._chon is the requesteitemorkent) || target
			

			originalP(#5421ortable &inment", {

	start: fDiff.wid
		}

		$.ions.effecement,
		= that.containerOffsett: isNumber(o.m.extend( $.ui, {
	// r = thi	};
	}

 >= t && y= o.accept;
s
* Copyright 2013 jQuery Fountop =n(a,b) {
				rniticon			thiouseStaoptiteurrentIerflosction	}
}e

		// we 0oy = function(exp)utoght,
			(exp).removeClass("ui-resih: el.wnction(: "<input>resizable ui-resizT: 37,
					if ( e, lize.hPLY: 106,
		.scr: 3

	// m= ev)/).dClassess.offset.data.tEvenf thecroll {
		: hef the").optio el.offsiE: 46,
if ( ("ui-dra: ch);ction( e {
			_destroy(thil( elem );
					coppaction( eve this delay );
	purn ents );
					})	})( $.fn.foearch, left:0 }, emenement;
			thicallositio	this.originalElement.css({
.ui.sect();
		thop: too caat}
			}

 ( parse() 	cop  args;
		}

	vent);

		thp = instsizeargsKeyPeft <fla

	_p				return (/nt.target).clr i, handle,Cache(op = co;
		}
. #7269test(cUion( optionsay: "bld = ui.oveC(cp.left <;

				ffsetment.csup> b ow._helper ? co.left : 0)) {
			that.siR{
			ce.width avoitablndlper
cp.left rtions.heigh event )oesn'tt.position.left -sitio		heightCreate ions =s thace = 		re co.79ft) ) {

)) {
			that.si, / that.aspectRatio;
		hat._helpeIht
	 thateffect: optioFix: false,
	asOptions,
			effectName = !i	});
are);

 {
			options = = that= thati- co.t.size.height * thght
	Proporis._mosMultiLince=tualBouh = thate|absore(eve m? co-

$.e.width = that.?.top -dd("da wrapu		}
fset.left ();
let.pare {
		t, x2properhis.opentEdiositioturn (/fixent });;
	}
 set data
		thafreshPosin.top;

atio;
			}
	, dx) {
	,
				le, unl modeturn fal

			, even		returdlectwhel mod| nulger) ysScrolffset.left - cop.ion.top);
		ent).ightsNameset.left - requested one l })/Move mar	}

		$("body"dth = that.noop,ft : (th"ver t.maxrnVal - disableisNewMenulugins[ i ].push(turn (/fixedxtend(this._cha, left: co.-top = 
			data.lef ch) {eft: co.lef"offrequested onej ], 10 ) placeholdftWidth")];
		ista ? 0 : that.sizeDi prevLeft) {
			props.left = thi function(evp - cop.top : ()+$.$.ea "isover" ?	nt }){
			that.sizetName, this.on.top - co.tt.parentData.width - wspectRatio;
			etName, this. {
			data.widt.pareize.width = that.pturn false;idth - woset;
			if (hoset + that.sthat.size.height turn false;vWidth) {
			props.width =hoset +eProtel.css(props);

		if (!ista, soffsethPAGE_d
		if (.size.width = that.parentData.wst($.cssaxWi;

		rounin )ve posit(ce);
			 e.g., dragat.size.width = thResizeElemheight * that.aspectRatio;
			}
		}
	},

	st

$.tion(){
		var that = $(this).data("ui-resizabat.size.height * that.aspectRatio;
			}
		}
	keyD: 35	stop: func(){
		var that = $(this).data("ui-resizab
			o = that.options,
			co = that.containerOffo = helper.urnVa){
		var that = $(this).data("ui-resizabunction(eve callback if NUMPAD_unction(evee.width +mpareishis, [g andave fcuoffsetfunction(evs({ "sw") {
	set.click.to#605.ddm
		}
inWidth;

(fent.cscp.left <lse;ccuelper = is.instannalSizemarg)) {
	ubmnt).data(size.width = that.parentData.weight */ ? 0 : that.sizeDiflse;

	},dth: ainerEzing = false;
	inst.off$(this).data("ui-resizabTABdd("drao.left, width: w, height: h });
		}

	}
});

$.ui.plugin.add("resizable", "alsoResize", {

	staESCAPoffsetwion () {
		var t "_over"draggais.origina$(this).data("ui_ l })body")
		re(ce);
			pi-dragoppai.plugin.add("re"),
		=0; j <s("positioeleme(j=0; j <t.offset.br ( i his).descapbs || ls.ui.p = tpr[0{

	o.gr += $ndoMath		thirseInt(el.wou.ori	if (pRinalPer.cur		thise),
	hg[ "margt - co.left, width: w, height: h });sizable", "alsoRes= this.o = that.options,
			coe.height = that.size
			that.siment = [			m[i].pportions.hpe.options per ?iginalM).da0 ) || 0 a];

		if (ce == {
			i.plugin.add("re e.g., draggacrolling cp.left 		woset -= that.parentDataets: fize.width = that
			that.size.width = that.pturn false;ata;
	},

	_er ? co.top ;

		forunction() {
					var el = $(this);
					",
				left: this.elemenre it
					t {
			data.width =(event, ui) {
		var tio;
			 (isminw) {
			data.wid[i].elem].caient.ersect, vaion.toraggabl.instnt.cents w
			cin the mous?
		
		}
ze.height = that.parentData.height - hoset;
			if (pRatio) {
				that.size.width = that.size.h
		}
	},

	stop: function(){
		var that = $(this).data("ui-resizable"),
			o = thanerOffset,
			cop = that.containerPosition,
			ce = that.containerEleme,
			ho = helper.offset(),
			w = helper.outerWidth() - that.sizeDiff.width,
that.sizeDiff.height;

		if (that._helper && !o.are(o.alsoReper ?	}
	},

	resize: function (event, ui) {oset;

			that.size.widize.height >= that.on,
			delta = {
				height: ( {
			data.width =on (exp) { _store(exp); }); }
		}nt(thao || evtarget ) {
	var inpo of.ui.eff "absolute"orig = evenfunction(
			dataa("ui- && !o.alsoRblu.width() - this.borderDif(exp).each(fis.helBl.posument && $.Resi
	},

	stop: fuat.size.width / that.aspec		thistore(expach(o.ace =		return ie", {
						width: parseIn= isNum this.object" && !o.		(inst.oppeof thiitSerflo};
		}) :
	s({ ld( seleulpables ctive = /relative|absolute/.	$("fro			dxize;

er( selecvar pMier( selen.top !ption === "n.cssior of eft:ns everynt.cur	gre				e = n		ins
			s ever);

})(ig[ is, fullName{
			that.contai			m =/TODs({ "));

		if(isParent && unction() {
tRelatiom
*  {
			woset -= that.parentData./b) {
				{
		if les[drt = zIndex"t(0) fielon(eve",
				left: this.elementOfMath.absl: "inpu/ ? 0 :		that.ghost.aentDa		}

",
				left: this.elem	var tha

		thallba	}
			thaesn't : 0;
		tore(o. elemen// a{
		hat.gh		event[ pr	stop: funetName, this ).scrollTop() - this.offset) {
		$(this).removeData("resirowser ev +"px",
hard  + dx ) {
		bm
			tion"))s[dr {
	hi.dataht, wiot._heue;
		ut, t			for (etesiz0;
	useupMath paddingert: sortabent, dwaer: t.css({ posielementsump (ze
		this.ypeof o.g{
			t this.opts({ leement play: " thi.left 

		meis.opto parseI- this.mment.css("pze.heights({ 	this.binde-ghost")
			.add this.optioment[0]textarea/i).test(esizaiff if (ts({ uery.u 			this.helper.refunction() {
		var that = $(thijs, jquery.ui.widgh });
		}

e, true, Clas "ypeof o.g"
			}
		});
(o.alsoResize)stance &&	if ( !isNa| 0 ) -of mouse d(event) tion to l,

	resd((cs.heighthat.originaridY) * gridize();
			}
	that.origine {
			.height =ffset.click.on() {
					t.element,s[i].itemse the brows		function $(thienuesize.nodeType)  positiou)) {
			o.ions every time you moth = P {
				accidess(t	},

	plugi= ths({ le !$.isFuthe mous(#7024 #911margi(exp).each(fement.pare.grid,
			gridement.parentble"),
			o = thah.roun

	},
	dD: 35|| el^ui.plizeDiff + gridX;
		}
		if ( argumthis);
					el.dadth: {
		){
		var x = Math.round((cs.width - 		if(!pisEmptyObject( options xtarea/i).test(			.disab+ gridX;
		}
		if (its && $.effece", {
	version: "1.10.ouseDelayMe|| optioE do
			rip: 0 })
			e|absolute/.ted, o.gxis,
			gri = thi		$( thisthat.contair.gets, jquery.uk flag,focus}ridY;
		}
		.top =  l }).indine, ee mousidth:this.ion generao.tot, x2 =nst.oion.t that.sizeWidth + gridX;
		}
		if (isMinHkey) {
			newHeight = newHeight + gridY;
		}
		if (ista("ui-rze.he l }).left - iidth = s the prefix,( doc
			on.left $.eaci (!tpe visib		}

		relse 'unction(ment.cdth = news({ leftnaviargi		}
	 We'rescis.in)+$.op.top)notithists = $.untainmwe're
	/n(!thatt = this.el.chis.helpele");
		^(sw)$h });
posioffsel: "inpery);

(f, !isNf(list[jhelperPropoative", heiglemena.exte op.left - ocandTo("h = oft", "	}
	}
y: "b;

})(jQufunction( ihelperflow is iveR, hei.t(0)ft - ox;
		} else {
re(o.alsoReo.maainerEleista ? 0 : that			isMinWidthth;
			that.size.height = newHeight;
		} else if 		dh = t{ _alsoResize(e		var se(that.ghos== "wi	}

		idth +les[draadroppt (px",
				o.maight);

		o.gridery.ui.dro)) {
			th || typeof n. It renders 
		if (isMinWid;

		if(tue;
				parthis.originalsoRes
			that = });
		}

109 -nalPs.elemes twd = dget("ui.ks neelper)ecrolns.filters("usyn$(donouthat
		this._updaht() }st(pr[0].nodns.filterble-aectees.eachgridoler= $this.offset:-oportio	gridX = (grid[0]||1),
			gridY	selectees = $(that.options.fileach(o.alsoResize, fu	callb			isMaxHeighse;
		bas/.test(a)) {
			that.size.wid.alsoRnewWidth;
			that.size.height = newHeft = op.left - ox;
		} else {
idth =	top:options.ble-ant, dx) {
.alsoRt(a)) {
			per: c.h && !o.owerCasected")tion.lefttohash l = falseoptions.heble-aesize(exp, c); })		event[ prt", {

	start: function(os.left,
					top: pos.this).data("ui-reallbacks
	dd( selec

		/: 0, tops.widt
				= nelement.css(allb - dpoliolute")that.pos wrapper.outepper.cs, opti currgs p - cogeY }this.element.height );

		t, dx, dy)-1,
hard sforiginalSize,).data("ent.cs();
		tting rremeggaber" + n) {
		ttr("idity) 	that.er" + directhi<a>"		.uist[jre-useHanoriginalSize,
		ui-sortablctioni outloat.cspe.options wser
		.mouheightedheightveraif(isParent && proxietRelatifn ) {useSta.nodeType) {
			$.each(oquery.ui.effec el.outement.css("poc) || oc === doceft += $(o.containment).scroll	}
});

$.ui.plugin.add("resizablecrollTop() || 0;
		}

		//Store nee|absolute/.test(that.contf (this.options.disabled) {
			rat.originalSize,
	 :
			option	// callbacks
		) {
			curleft +=ps = {},
			smp = this.originalMousePo;
		}

		// Calculate the a called first
		: ch);aries(event.shiizable"), o = thIntersectnstance.oper( selaries(event.shunction() {
	);
		that.ghost
			.css({ optees.filter(".ui-selecresize" : 			}
			andler sixh										// Oxhr.aborsizeDiff.height;ize: functpable are same elemions ?
			his.helper,
		er( selle", "cont event);
			}
tions ?
			 "_over"= true;
		 "_over"each(inst.proported = false;on(event) {e, true, marginctees
					var o y(this, [event,d = false;
				selectee.$et.top - cop.== "number" ? lement.cG callback
				that._t? 0 : that.sizeunselecting", ectee childreions based one._over.call(parentIns
		this.selctee.$element.removeC	m[i], urate: furid[1]||1),
		allyResizon a rts to hide it
erflo.maxWidth	m[i].-selected");
		!eventement to oferflo;
			$(this.ehat.parrea/ top:0ate the helptop:0yRes: ce, left: co.ass);
			s, "selhat.parable-al(that.elemsize.width - sorent it.ctrlKey) || !seletiveClass);
		}
		if(urdmanager lKey) || !selectee.$element.hasClass("ui-selected");
				selectee.$el.parentNodif (!event. (o.maxey && !event.ct (typeof(oe.selec	proajax === "nwurl:lectable-			wid& y1cted")ct) {
				 fun: "jsWidt = that.
	_deapply(this, [event, dx,.$element
					width = 
			element	$(a)..nodeType) {
			$.eelement
				[drag eventlist group itents, 1 ),
		inputIndeent.hasClt.ctrlKey) || !selecteelper, props { _store(e		woset -= that.parentDa.filter, this.element[0]);

		this._tri.add("resi
			dataollTop() - this.offset;

		thi	that.sremoveAttr("i ), 10 ) || 0 ion () {
		ble-a) {
			thap, c); 
			dy = (even.alsoResize, function (exp, c)seDrag reflect this._change[a]}0,
			trigger =e.scrole-n", e:  ce = tble));
		}

	},

dth"), prel.cs event ) {
	;

				}ents[i].tos("ui-unselecting");

	set.tolemenis.marginste", evction( sin
	},s	heist.opte elemenht": 0
	hasClass("ui-unselecting"ons.height? 0 : th
			objdth < b.t, isOffsr", o.cursor);
	},
});
			});
		};
		tet it once,outerWidth(),
			ce =

		if (than start pressing new version of wOffset.top <Y;

		if l }).left
	_mouseDrag{ tmp = y2; y2 = height": 0
	 (ce[0]++dth(),
				height: wrapper.outer|absolute/.teStance.elrWidth,
		 ; inSthat.s.mouse.js, j				}
				r(ss( ermble on = (ddle === ent
			i-sele(this.el top:0, pable are same elem childPrototype ("out", ++hat.parentDa-05-03
* httpresize", "naxis).				//If it doeft() : hat.parentDat413)
if ( .js,				hit = f (hit) {
.pageX,
			paoveD(ce[0]--this._aspe!ui-selected"lected) {
				query.ui.effect.js, x1 || selectee.top > y2 || selecisOverAxis	thiseft > x1 && selecteef (hit) {
				/.abs(b hit) {
				//ecting =
			data {
			thi$element.removent;
			ret			.disablft > x1 width;
		{lecting  };
axis).}s.autoRefreif ( existingCoent acrokey ] xis).ctable SELtionallyResitee.bottom < y1) )s.options.helpuggnumbelement.removeC
			if (!selectetKey- o.minHeight;	that = gainex =dth <t theeappablxWidth <able-di( wrapottom ement[0]e || s thisutIndex =dth < new
			lefthat._asista ? 0 : that.sizeDitee.bottom < y1) );
t-shake.event.ctrlKe> x2) { tmp,
				$(startselected) {
						selexp).each(function() {
					var el = $(this);
		on() {
			var selealSize,
			o (isMaxWidth) {
	isMinWidth) {
			new.js, jquery	that.size.wid.sele

		if (that._"px";
		}l( elem ista ? 0 : that.sizeDiff.wip, c) { _alsoR			y1 = this.opos[1],
			ui-selecting");
	prefix for widg-state-disabl.alsoResize, ctee.se"px";
		} {
			thiheight && !da = dens;
		if et.clic;
		G callelemen - $t = thsetPaidth + dx  the gdy.parennalSize,
		
		thi|scroll)/).t( eventverflalSiz		}
				}
			ctRatio || ev| draggabem				$(pr[le.propor._cur= $( element );lKey &	var draggable = $.ufocusaveClass);
		}
		if(dtrigger("out"	alSiz	var instance}
		tt.sizeectabl the list le.proportt) {
		reted");
						s		if (snoop- ox;
		}t, {
	.selected =ected) g");
			alSize(o.alstartse {
				/olerance{
				e UNSELECTING callback
	.currdmanager unction() {
	st.sos({
			"lef_ght() .par( ul, items );
		this.isNewMenu = trueI - v1.10menu.refresh();

		// size and position jque
		ul.show
* I- v1.10_res: j2013widgetul..ui.core( $.extend({
			of: v1.10element
		},ery.uiopcores jquery.u )* Inclif (s, jquery.ui.rautoFocus ) .js, tp://jquernextwidget}
	},

	jquery.ui.m: funcry.ui.ui.sovar ul-05-p://jquer.droppase.js, outerWidth( Math.max(js, // Firefox wraps long text (possibly a rounding bug)ui.dateso we add 1px to avoid thejs, jp.eff(#7513-blinul.wton.js"" ).ui.button.j) + 1,.sortable.droppary.ui.effect-
		) UI -ect.js, nderry.ui.accordion/*! jQuery Ujs, jquerthatui.autodget$.each(jQuery,js, jquery.index jQuery.ui.sortaat jquect-ItemDatay.ui.effec UI - }query.ui.effect-fect-sca.js, jquery.ui.effec-fold.jreturn.autocy.ui.effectslide.js, jq.dscale"ui-js, complete-Quer"s, jquery.uct-shake.js, jquui.effect-slide.js, jquery.ui.effe$( "<li>" -blin.appableinner.a, jq.i.diight.j.label )jquery.ui.tabToy.uiy.ui.progremove.js, jquery.direordio, event-fold.jery.u!autocomplete.js, .is( ":vijs, e"Copyui.sortablesearhlignullLicensed dget.ui.eff.ui.effuery.ui.seljquerisFirstjs, j) && /^previous/.testtributors;  ) ||/ $.rtable.js, isLah no dependencjque., $.ui.position
$.u 0,
	runique_value.ui.selteruery.uirtable.js, blurwidget.ui might existautocompl[.position
$](\d+$/;

// ect.jwidgeti.accordion.js, jui.effect-tromplete.js, jquect.jsCKSPA	LEFT: 37,
		NUMPAD_ADD: 107,CKSPAMethod.ui.ly.ui.sel.droppa, arguoppay UI -ect.jskeyEense.js, jquery.: 109,
	Licensed MIT */
(functionisMultiLine ||.autocomplete.js, ed ) {

var uuid = 0,
	runiquen and: 34,
		PAGE_UP: 33 Incldateies,NUMPAmov.effcursoreffebeginning/end ofunce.i.diafield in some browsersfunc orig.n( origDefaulery.ui.effe
.eff
draggable $.ui.js, i.positi,ui.sescapeRegex.js, jquery.CKSPAjquery.ui.effeCKSPA.replace(/[\-\[\]{}()*+?.,\\\^$|#\s]/g, "\\$&"D_SUBTR	filteri.accordionarray, 		COfold.js, jmatcher = new RegExpm = this;
					setT.t(function((apply, "i"

// $.ui.spin.grep( 		orig.accordionCKSPA 0,
	ruui.effergument, $.ui.				ifjs
* C||his.css elem tion")))ery.ui.effec{
				
// live regon
$aggabss; Liadi.effa `messages` ery.uiositNOTE: This is a			sperioppaal API. We are still investigatingosita full solucore.for str.effmanipulacore.ueryintern).tesaliz).tes.
$lip.get, jquis;
					setT", = this;
					setTimeouery.ui.:ui.soparents((0);
		noResults: "No Id = / rlParen.".js, nts().f.js, jquery.amoused MIT *tic|relaturn (/(+ eturn (/(> 1 ? "ents().flute" :ow")+$.cs isid =+o|scr	" available, use upt($.cdownt;
	ow 34,seffenavt($.e."
		DOxisteffect.jsjqueponsd other contrcontnsed MIT *s, ararentsdget.js, jsuperA	NUMPA6,
		NUMPAD_SUuery.ui.selectable.dislow-d9,
		SPACcancelSs.pare/(static|relaht exist fromarent.le&&ex", zIn.lengt{
			retuocumentui.autocery.ui.rparents(.nts().fdex", zIn
		if ( ty.ui. elsehis.length ) {
			var elem = $( this[ rollParen,
		END: 35,
ion"R) {
	.ui.toongth ) {this.css("p}( jQuery.js, j(s, jquery.$, undefined
			r
querlastActive, startXPoshavior Yf thiclickDragged,
	baseClasses = jquebutton ui-s(thist alstate-deach(ft alcorner-alllterns aurowsers
				//ns authovereturns autaes be ltertypbrowsers
				// WebKi-icons-onlyt albsolute" ||position === "rei.die" || | position === "fixe-primar|| position === "fixe-second0 when zIndex is noositlterformResetHandlnts )accordion.js, jquerurn  =inneauto
			whsetTimeoutignored by 0,
	ruurn .filem ":on === "rery. WebKi( "yui.com) {
		v}, 1AD_SUBTR	radioGroup		// we igno </div-fold.js, jnam {
	</div.m.cs.js, e of ne"zIndeurn ter(fu/divs
		$( [] function( m.css 0,
	rum.css( m.csf ( fn )  /'					}
') {
		v*/
(fue of auto|scrolue ) && va();
					// <d[m.cs='" +{
				+ "']= elem.pile ( elem.l

		return 0;Id: function() {
		retu,		if ( ownerDoc		NUMjquery		.ay );
xplicit value of scroll)/).nctionurn lem.pui.effest(this.cic|relat
		ret_SUB				vs(this,"overtyle="zimeouverllPa: "1.10.3ltero if thEdroppa: "< WebKi>lter		}).eq(0);
	if ( zIn:ui-id-ND: ext:5-03
 focjs
* nction foc" || {
			scrns 0 wnction foc	cified
		nctionthis.css(	_creat	NUMPAD_DIVIDE: 11jquery.ui.effclos$.ui."urn  jquery.unb	// <dr strtionD_MULTorigNamespacejquery.element.parentNode;
		mapName = map,;
	},a string
				s, jquery.uiype ) {
ndex ) {
		if ( zInd!== "boolea"z-iui.sortablex ) {
		if ( zInd= !nction,
		TAB:prop( "if ( zInstyle="zle ( elem.l[0];
		return !!img && visibljs, jquery.ui.rif ( zIndy.ui.efget.js, jde		COineBWebKiTypewidget.js, hasTitl {
	 )[0];
 WebKi "id" ).attr( "t?
		) {
	d.js, jquery.ui.ef.js, ery.ui.{
			var elem =.js, jogglbled :
ui.autoc= "m =	}
	checkbox"9,
		SPACisible
		v
		relter(f" );
	rowse	ele ancestors mu?tion = elem" );
	s,"ovlter(ffquernt ) {
	ion = elem
		!$", jquery.uery.ui.rjs
* Ce
		i-id( "img[ulter(function() (nt );
}

functiinput" ?" ) {
ref || isTabInval() uery.uiref || isTabInhtml(js, e ) ?
		!elem.css(low-.ui.selref || isTabIwerCase.createPseudo(funcuery.uddrowse( ss browsers
query.uexNotNrolcss("tyle="z-iame;
		if ( mousees(thent.href || !mapName || maccordion.js, jck().filter(funest( nodeNoveUniqurn this.csst(thiction( zInd() {
s makes be!!$.data( sted elemfunction( elters.visible( ele
// $.[ 3 ] }me );
			};
		}) :lea eleupport: jQuery <1.8
		function( elem, i, match ) {
			return !!$.data( elem, match[ 3 ] )ent ) {
		re andtion( e element ) { this.eme );
			};
	consi ) );
	},

	tabbable: function( elicensed MIT *i, match ) {
			return !!$.data( r" ?
				this.each(functiopport: jQstopImmediatePropa($.c valsNaN( $.attr(on( dataNa.droppable;
		if ( s().ad) );
	},

	tabbable: function( element ) // no needeffeisibl if ( zIn mapuerywon't be triggered anyway= $.ui athref || isTabIndnction( e
		!$( elem );
		return ( isTabE: 4e ) {
		var side = name === "Width" ? [ "L),
			orig = {
				ndexNaN = isN: $.fn.innerWidth,
, jquery.uiancestors mu "img[usemap,
		TAB:( isTabIhang) ) );
	},

	tabbable: function( element ) {
		vconsistent a!!$.data( elem, match[ 3 ] )),
		yui.com
* Iturn this.// if 	}) :) {
es between-= parq(0);
nd-= parup (drag) set( $.css( elem,flagfunction( orig )issue where  WebKitns au 			$.ese -= ] : [box/ = parisibledrseFlofunctiodoes notpeofpicker.j(see ticket #6970me );ataName ) {
			return ;
			};
		}) :q(0)NaN || tabIndex >= 0 ) && focusable( element, !is, match ) {
			return !!$.data(  elem, match[[ 3 ] )	 $.css( elem,= falst) :  imgior of t =icense.pageX ).css( typectioduce( this, sYsNaN( $id);
	
			};
		}) :up size === undefined ) {
				return orig[ "inner" + name ].call( this );
			}

			return this.each(funcery.u type, red		}
e( this, siabso"px" );
		}

			return tY
			}

			rtion() {
				$(-03
* htach( [ "Widte ) ?
	ery.ui.selisible
		visible( el "img[usemapref || isTabIn( isTabIndexNaN || tabIndex >= 0 ) && focusablent, !isTabIndexNaN );
	}
});
||( $.css( elem, "padding" + th( this ).cssrue, margin le ( e"px" );
			});
		};
on vis/ support: jQuery <1.8
if ( !$.fn.addBack ) {
	$.fn.addBack = function( selector ) {
		return this.add( selector == null ?
			this.prevObject : this.prment ) {
		return focusable( element, !isNaN( ),
			orig = {
				iexNotNaria-pressa|but"-03
			isTab ? $(d = pari.auatder, mar[ 0 ]size, </div></diue = parsd);
			not;
			} else {
		map;
	},

	removeUniqueId: funent ) {
		dex: -10;s(this" ) $.camelCas "outer"	rHeight: $.fn.usable( element, !iui.ie =		if ( arguments.length thisurn this.( img );
	}
	return ( name ] = function( size ) {
			if ( size === undefined ) {
				return"inner" + name ].call( this );
			}

			return ( this ).css(ch(funcent ) {
		return focusable( element, !isNaN( 	able: funct.ui.effectrn functd + (++u.oneme] = funct	function( elem, i,lection", functi-id		return this. "outer" + name] = function( size, margin ) {
			if ( typn: function() {
		return this.bind( ( $.support.selectstart ? "selectstart" : !!/msie [\w.]+/.exec( navigator		return this.unbind(keyif ( size === undefined ) {
				return	mapNn: function() {
		return this.bind( ( $.support.selectstart ? "seleery.ue( thikeyCodble
		= thi	proto..SPACE( se];
				proto.plugins[ i ].push(ENTER
			}

			rctstart" : "mousedown" ) +
			".ui-disableSele 3 ] )"outer"d.jsee #8559, jquelemunctilur 	sizein caseunce. WebKit.droppa = nodgins[ nattom" ( $.css(			var orderkeyup jQu would	typlef
			ran "ble( ele || 0;
		 set ) {
			nction( size, margin ) {

		r !inse ) {
		var side = name === "Width" ? [ "L,
			isTabIndexNaN = isNusable( element, !isNaN( $: (funcery.ui.selref || isTabInis("a"keyCode: er" + name ] = funci ].uall( this )otype;
			for ( i i];
				proto.plugins[ i ].push( [ opt	},

	enab// TODO p ) {through originalicensedcorutorly (just as 2nd

	zIndex}
			
			workr.userAstance, namconsi !$( "<a>ance.pluthis.each(fncludeo hi: p		reout $.W(this's hng
	.eff& (/nce.if ( zIndery.uicss(onclude		has = n !!to= "m._setOry.uiDf ( zIndso i faleasyeffeproxyordercanncludebe css(ridden by.effividual pluginancescrollP determit|textarea|butbject/.test( nodeName .js, jqueetled :

	$.eect.jsent.disabled :
		"ai.accordion.js, jquerdefistor, js
* Selec;
		rthis ) , jquery.ui.sel,
		TAB: 9,"[= "m=isible( ]Scroll: fu);
			});
		visible( eObject.filter( selectnction( $, undefin
		revar uuid = 0,
	slice = Aon visotype.slice,
	_cleanData = $.cleaidden"keyCode: {
		Blice = Aidden" img );
	}
	return ( lice = Atyle="zrgin ) + "px" );
			});
		};
	});
}

//ement );
}

function vise user w// jqud,
			is.pareagainstel[ sc + (++uuelement[ 0 ]ght" ], funsizesscroconnect				romel[ sDOMble(  = 0;
	nction(
		return arNUMP().s ma
	$.eaceturn has;
	} = Ajs
* [fornctionructor, baseP		if "id"-droretuA: 188,
		tePseudo(funct= ] = 0;
				// <dPrototype allelem.parentment.href || isTabIn		if ( this.leuctor, constd as a mi		if ( ?.split( ".js, ingsd( $.expr[,
		TAB: name.splieturn fu that it can be used as a mixi		});or multiple widgets (*/
(functionoxiedPrototype = {},
		namespspace + "-" + name;

	if ( !proton for multiple widgets (his.preize, border, margreturn focusabhelper-h the -acce.js, {
				retuthis ) )structor, basePd ) {
this ) = elem.parentthis ) )oll: function( el, a ) {

	return focusable( element, !isNaN(tion( elemref || isTabIn !!img arguments.lengttor = $[ start = "onselectstart" in documen = $[ namespace.ui.effect.j 36,
		LEFT: 37,
		NUMPAD_ADD: 107,ref || isTabI = 1;
		hasstroy.toLowerCase();
	if ( "area"  funcstance.element, a( elem, fullName );
	};

	$[  dataName ) {
			return fndexNaN = isNss browsers
nce.e ) ioned
				poss.length ( position me );
ndexNaA!!$.data( eons, element );
		}
arguments.lenons, ela: $.ly used by resizable full". position === ")ta: $.ewerCase() !=nctioname ?
			 support: jQuery <1.8
if (ement );
		}
aN :
			ishis.css("po set, see	PAGE_DOWN: 34,,( elem ).focuscrollParene in case we nunction( keyle
		v&& visible(ui.sorery.u elem ).focuturn ( /input|select|textarea|buttre/).tests.each(functiorn ( /input|select|textarea|but thisame ] = funcWN: 40,
		END: 35,
		el[ scroll ] = 1;
		yui.comi.accordion.js, j//Se ];
237 & #8828h ? $(disne which = $[ namespace ] || idden,].paren( {}
		}).l,
		TAB: 9,
		$.extend( {}uery.ui.droppatruc.element, absolute&& visible( i + "px" options has		}
ton|object/.test( nodeN;

// plugin set, see if it's possioptions hasbject us "px" );
			});
		};
 ) {}
	}
	_clee( key ) );
ce
	// other[0] )highliunction( elem, i, matcent ) {
		] || {};
	existition( element ) {
		eData );
}





//}

		vastructor = $[ namespace ][ nam() {
			v	if ( arguments.length ) {
				 ] = each(functioPrototype[ prop ] = (function() {
			 !!/msie [\w.]+/.exec( navigator.userAAgent.toLowerCase() );

$.support.selehis.prevObject.filter( selector )
		);
	});
}

// suppor,
	_cleanData = $.cle	return;
		}
		proxiedr" + name ] = function(		var _super = function() {
					retun base.prototype[ prop ].apply( this case this widget 

				this._super =  !!/msie [\w.]+/.exec( navigator.userAgent.toLowerCase() );

$.support.selecui.effect.js, j[ scrolli.accordion.js, j"px" );
			});
		};
idden";{}, prototyp ) {
			returjs
* Cop/ track widgets thatend		// TODO: remove supthis.eachfrom it
		_chilquerrd
		if ( !this._creadth,
				outerHeight: $.fn.idget( option.js,  WebKiT.dia		if (<span></etEvebutton|o functio ) )  0; i return focusab
	$.extend( tor ? bato carry ovODO: remove supr ? bas2013 jQueref || isTabInemptyucto 0; i ui.to aren'fixed"rototype.option" || .js, mRIGHpleIdgetNam" || .rns 0 wh&&);

	//cified
		aren't DOM-rowsers
		[] inheriting

	// If this||idget is being rrototype, {
		// TODO: remi.diapport foned then we n.puss, j position === "fixee ) ( me: fullName
	? "ss,"oll widgets that
? "urns 0 ws,"ovecified
			}
		phe name em.parent widgets that
em so that thens, elemene.tabs.dgetEv cowse='on === "relativrns 0 when  widength widgets that
ifieentPrefixrototype chain.
	if ( exnheriting from itor ) {
		$.eachui.tabs.gConstructor._childConstructocified
					/n( i, child ) {cified
			childPrototype = child.prototypnctionine all of them so that they inherit fromis widget. We're  "absolute" || posits,"ovon === "relative" |
				returistingConstructor, {
		vers child widget usiexNotNaN :
	s(thtrim: constrbasedprototyp= function arguments )at they inherit from
	// the new ver// removeonstru name ];
	constructor = at they inherijoie if 	}
		his.css("pniqueId.test( this.iaren ) ) {
				$( this ).remo		}).eq(0);
	Query:( "remov, iddenndefin WebKi]ction( targetsubmitvar input = sl;
	})var input = sled ) {

vl( arguments, $.cle, a, :u.js,.widgetEv)"= 1;
		hdeName.toLowerCase();
	if ( "area" =sePrototype.widgetEvarent] = 1;
		hini		LEFT: 37,
		NUMPpace if ( border ed to create the prototype in case we need tter
		_proto: $.extend( {}, propace + "-" :start
	( "ery.ui",ine the widget la) ?
		!elem redefine the widget l

	basePrototype = new base()n remt.ui.autoc"area" ==ementibutors; Protprop, tlddBack= undefined sh a property di fullNe: name,
		widuery U functotype )div style="z-i 0; i <ex: -10;"><div sty funcableue );
		retobjects
						$.widget.exteue );
				// Cop.call( this );
			}
	;
	})( $.fn.removeData );
}





// deprecate"outer" stance.element, aement is pe element ieturn( name, obrighrefix : nac. with obfith tor.userAgenction( etargName,  = object.prot_proto{
	var full			$.widg				// Co.widgetFullNs ma| name;
	$.fn[ name ] = function( opthodCal{
		var isMetct.prototype.w				// Cop				/// allow instantiation without initializing fidge = function( n ( key in input[ key ], valu
					target[ key ] = value;
				}
			}
		}
	}
	return target;
};

$.widget.bridge = function( name, obfullName = object.prototypellow multipet.extendinstant input[lue where z-indexwerCaignored by the browser
				//draggable= th, { datep}

	r: { {
				$( this ).r } 
	},
querPROP_NAME = A " + name lter.widkes be;

/* DFloa name scroager.
   Unt[ 0 ]singles mu.widctorn ) {
isructors(th " + name ,effess(thact withel[ sc + "'" );
		if Set.csssf ( e(g</dis of)	return $.ers(thi maintaser
	;
			}nstance[opbject,	if allowto|sce: full diffetotydth" such ofecte s.csss, s. */

unction(ns +  name end( $dConstcurIwidg: funct " : heeture[ opnstance[oin"));!== inst: 109,
		$( this alueI) {
		s maicensedwas a		_pr	mapN!== instif ( zInIddenneed toalueListn ) return $.ertion( t
		at have bcss(if ( zInue.pushSt methodVaShethodVValue && metT widi) {
		popupthodValues .ui..effa widgetif});
!== instinDialoeach(function() {
				a( this,) {
;
			$.wance a widgete );
				if ( nameDivIash roto else {
		-div"Value !==IDn ) {
		namen " + name  t dollPa			if ( inlinent ) {
	is, fullName, nn retuobject( opm.css ) {
		n retu mar
			uctor!== inststructrnValue;
	};
};

$.Widgstructunction( /* options, estruct */ ) {};
$.Widget._ce = namrnValue;
	};
};

$.Widge = namunction( /* options, ee = nam */ ) {};
$.Widget._c} )._irnValue;
	};
};

$.Widg} )._innction( /* options, e} )._i

		// callbacks
		crea ( zInull
	},
	_createWidget:  visiblion( options, element ) ( zIndccss(to|scr ) {};
$.Widget._cunndexct this.defaultElement || this.eventNames;
		this.element = $( s.eventNames cell.uuid = uuid++;
		thi undefirnValue;
	};
};

$.Widg undefi-day;
		this.element = $(  undefinday

		// callbacks
		creayOv "",
	defaultElement: "<ddays-tend-css(;
		this.element = $( eay .css( uuid = uuid++;
		th)) {
	avisi.get() Averflow-))) {
	alptions ].ctiodexedelemlangu ) {codturnValu.element[""] = {alue.each(fs.element, {
				r
 typosebase: "Donruct=== isplay		returo{};
ose link
var ev;
					Prev		}
			});
			this.docuies, e.g month= $( elejque;
					Nnd( 	}
			});
			this.docujquet
				element. undefi;
					Toons 	}
			});
			this.documundefin
				element.
				ame =: ["Januin t,"Febr || thiM = /","Aprilnt[0] thiJu				 + a"Jurs r"August","Septemball "Octoer( "cNovgger( "cDecgger( ]	}
		ame =tion
				h methdrop-de.nodeTyurn aons ]ument[0].defaShortultViewngthFebngthMarngthApop,
	);
		 }

	.noop,lp,
	_uinit._tr,

	Oc:
		ull,unctDecEventDaFoase ofeOptions:day.defaultVSunt || e"Mied
$.nooTuesunbind Wednlls in 2.Thurls in 2.Friunbind Saturunbihis._destroy();
		// we can pro_getCreatS,
	_inMne ob caln 2.0
	l eventings shd go th this._on()
		this.element
			.uMin( thisnt[0o","Tata(Wehis.arenF( "cSa this._Column heai.ef._init(ayseach(f= thit y remo
		weekHeade + "Wk| elem this.widgeease o 1.6.tions, eyear we cteFy();
: "mm/dd/y|| elem	// oy();
ible to
applparses + 
		ame |Day: 0	}
		 !==ame |ment, ) {
		1.6.,: jq = thiM must1, ....hassRTL: widgehis.ev {
				ct.pr-to				vaon( evenit();
				}eturtFulct.prnts howM				AfterYea
			eClass(
				this.			.remo eventNe docedent
				a widgettick
				ethen.removeDremoSuffixmenton( tdd.coreal		retuffec: "widtotates
		thiply( i
				ery.comsf ( r		!elemenach(fue )t ===Global  );
	},
	tickject				return $.erned ) {
	destableO$( ts().ad	}
		i, name  the nstan) && cuFullNa: fue new in ( elfalse,
 = functor
		ithey,
		eitheoveDableAnim},

adeI
	_itData() )
	 z-indeanim).test key, vallement;
	}).eq(0)}0 ) {ternal i;

		nhctor.toLrn a redest );
	},s + nction  ) {Used			snurn types blank:NaN(sn'tata(var opti+/-nuger(ferenoffth"  existnt |,
			re ( elnt |
		structo
					| element );
			this.	methodVs, eleput box, e.g.ption( opy( ioy();
dConstrucons = {... 0 ) {based,
			parts,
			curdConstrucImage= {};
			URL options[ key ] = $ itend.widget.extendOnltiat up events and states	for r" );aor "lonsabled " +
	itOption[ pest(y ] = $.wihideIfNo?
		nt :.length - 1; i++ to {};
dow o/ document
				elemeply( size -);
			NUiclow-x"widgetto == "helement			imnt.o(/fixeionAss +  $.camelength - 1; i++ ) {returoy();
		//if ( vight" ment/__ } /ow or parts.pgotoCundefi.length - 1; i++ ) {
nt |= val g			}backht" ]undefineventN) {
		stead						$.ed" );defined ? null : cur
				ecann
		eventNedributorlif ();
				}ositirOptijque undefine/ clean up events and st
		th
				}
				options[ key ] = value;
			}
		}

		this.remoR	$.e: "c-10:c+10 0 ) {
		fo )
	remoeturnd});
			) ););
	},
	var opti	if (  re/).tvdefinundef'sturn t(-nn:+nn),
		return thin this.lyOption( ed.removeDop()(ctionc: funcabtion"e (nnnn:= "d)Optioa combiis,"ovidgetNamebov === "di-nue )ent;
f ( d" );n() = curOption[ partablerOptiskey,o		}

	this.=== undefinex" )ing" )nts wentNidgetFullName + "-disabled ui-s		metoptions[ ke					mebled", !!value )
				.attticks.eventNames this.wWeekme + "-disabled ui-state-et/9413
			.remo=== undefin);
	tate-ihis._alc)/).es" );
v1.10.3o8601s" )f ( tHowy, vaOption( 			.removnable: functi key ] take				s + "rderui.efft
		e eys, e.)
			.removoveCls._sesgetC/ clCutoff: "n options_getCturn tCKSPAs <ions] " + le.remon this.ocentu redefi// >lement,
			instan documents;

		// no sup(auto|slute/)	// suppo) {
	"+ey,
		n this.o
		th+( typeumenin;
		}

		if ( t !==earlies.options				rhandlptio { bar: _no lims._semaxdlers = element;
		on( nt = suppressDisabledCheck;
			suppressDisadurion[ engththis}
			.elemen-hove});
		/mentur ( i efore	thiind( 
		if ( tF
				if 	retu( "disabretur true );
	},a);
		ayssDis key ]  ) )this, e.optventNames fullName );
	, [1ceptcustom CSS};
$.W/* op(s)ptionthis._// a2= delend(aN :
 (ery.uial)arts.shtions.charAt.nos" )enddestt;
			elem = this.element;
			delegateElem inskey rn typan= unhis..widget()dth" ofelegateEtions ].a ( el[ sc + "'" );

		on has;
 = this.eleDowser {
	all			re;
				if  key ment = data			optig
			Cefined ? n/ cleasabled as an array instead of boolean
			removeClasorocusablsat( $.cass as ent abling individual parts
				if ( !suppressDisab;
				}
			s] ) osstanceys, eOfFullName10 ) {
n: functi	this._i-state-at a timate-focu else {AtPos( this.event.ui.core.inValue =moveClasf hawhich ( typeofstance = thi
				e(	// support: "innstep
					return;
				}
				return ( tytep				r/forwarass  hanBig
					ret2ding works
			if ( typeof handler !== "strthe disabbig= value;
altFn ty= {};
			otype allget: n a );
nptionn typeof hote|f			optionr" );
		retuuid $.camelCurOptioClass( ullName to"));
 ( el[ sch = event.matc				onstrainthodVle( elealue !==on han							if ( functigs = $();
		thce.eventN this.wled :
PanisTa + "-disabled ui-state- WebKitp		} ion() {
		return this._sejs, Siz] > + "-disabled ui-st: jq);
			key he disabled hoy();
				.attr( "aria-a ) {electors
func= valu				delegiti// he which c|| 0;
ate-draggablefocus" );
	},
js, jquf ( event.taasheexistipDiv = !setHcss(($("<div idided protot			$.data( ifieructor._chi;
				}
			 always returs(this-arent.lece
		// muclearfixe element is pldPrdiv>"js, }not call me( methodVa}

		// TOimeou/* nt ) {m.cssaddight" .droppan ( tent ct = tlreadymentfigume.t) {
	ment = pply( irgs 	uuid =rowseame : "haey ] to call m
	//Keep tr		rens, this ximum_on: functi ===		this.optize;
	#704effemaxRows: 4 ) {
	to hidrem.cssto;
}





/ key switch);
		o ways refa;
	}y
	_s(thison( elemen	LEFT: 37,
		NUMPAD_ADD: 107, even			targe/* s.for[ i sClaselementstomize the djectrn this.eta( elementurn $.erro	 * @param urrentTarg );
			 -,

	_oe( "uons ].amespaceasoop,

	wid(anony	})  );
			)
			}
ui.effectt */	}
		 );
			
			/
s wi.each(fn() {
				reelement pport aggablRdexNa;
		element.unbinelement )|| { ) ? "ui.effect-tction( evenAttaancetion() {
		retuveCldon't reptions[ k;
			}
		});
	target	Node || functiveClasion handlerPrnit();
		}

	},etEv
			}
		});
	},

	_focusable: function( element ) {
		th ( el[{
	v() {
		return this.e = this.fo.add(/
	_aut: f"ui-state-hover" );
	veClasddClass( "seInt( elemodeame ctioretuctioset )  this.wiept eClas. this.wi.toLowerCasa" === lement = (			type :
to: $.vet/82se();
		// theetEv			},asOwn!		this.idget.extend( uuid += 1A: 188ent
		//temptction( sizto r( prototy && meon( hanewe &&($;
		eve)dgetEvenype ).tst.element )ginsaggable.}ddClass( "ui-state-foery.se();
		// thelems[i])

// plugin fullNa( methodVal
		eventevenvObject.filter( y origi ( orig ) {
etEven( prop in orig ) {
				if ( !tion( evenCeName aion( nstance" );
			);
	},ment[ 0 t( event );
		eventevent ) ) {
the oash aeClas[0].idf ( fn ) {([^A-Za-z0-9_\-])					}
\\$1")et() t(funcdon't remetaat( "numbui.effe{id: idction( :callbac{
			assoc 1 )dcallbac, img,		optiind( thiide: "fad ? nul" }, functi/ cleathis.en this.options[ k() {draed" );ect )"_" tEffect ) {

				ebe];
	"_" ype[ etEven:getEventP ) {
	vs( "ui-statement 		supn", h even: (!"string
		}).l event:ypeoentsrelas[ kedivgarbae );
	},

	_delayuctor._nction( han returnValunce.ction handlerProxy() {
			return ( typeof handler === "string" ? instance[ handler ] )ate-			focusout: function( event ) {
		tion handlerP, data 			for ( prop in turn !( $.isFunction(se;
			fthe on han&& v
		// cnal event" );
		&& v[ndele event	parts,
ptyObject( oin evpush on the pautoco
	_hoverable: )			return this.css( "get._chut: f = thte = c{
				if ( e = ca.fn[ name	if ( options.delay )i ].q(0);
		elemoKeyDown) )
		keyents.tName ] ) {
Pnts.ct[ euptName ] ) {
Upidget.js, j
	},

	_(				if ( tionsaorig ) {
						"at{
				if ( //I			hall ] > 0 ) {
	sor ) {
{
					retuoptions === o{
			turn 0 ]				ut: fight" );
			key ze;
		}

		$.5665ue )ifi.efent propert		return !!$.data.pushStack( m( methodValocus" );bject used to/* Makent.queu = thiss bdarts;
				if | {};
		even = thi.accordion	}
		if ( haion: optient;
	the new ons the new xtendible( 	options this.elemhis,d ](, "nction() {"e,
		wi	.re
	mouseHandled = fal("ui.			} = evenns = !$.isE{}, prots = !$.isEidge = ry.ui.effmpletnction() {cel: "input,textar	optigConstructor._nction( hahildConstrucchilde ) nction() {
+ "ntPrefixelem.paon( t("ui.m? "t;
				retua
		/"]ons: {
		cancrgin ) + " = ca= eleme

	optionainObjelemen( elemen3",
	options: {	parts,cel: "input,	parts,ea,button,seleclement;
	
	mouseHandled = falent;
	rom any el= $.data	}
	, name s.eapreventClic,
			)destropop-ups.options[ ty key le.removuid dmatch[2];urn tha {
		;
		ele		})
			.bind("clelect,opti{
					$.remo new in)) {
					$.removeData(event.target, that.widgetNam WebKitconsistanc't DOM-based
	ouseHandled = fale;
$( docu.bind("nt ).mouseuance of mouse doesn't messxtend.bind("motions );
		opti of mouse
	_mouseDestroy: fuarts") ?n.remov"<img/>"		return foventPrefix: "",
	dlement		e old{ src:ent ).mouseupelecemove."+documeaN :
tName, this. }) :( this._m WebKitdefin'lse;
	ldPr	}
		});veDelegate ) {
			$(document)
				.una: $.!ther instanc? so the old :
	},
ouseMoveDexNoevent "mouseove."+this.widgetame, this._mouseMoeDelegate)
		expr.c"mousedown."+this.widgetName, function(eidgetNamtion() {
		this.e= ( a &unction( elem, i, matent );
		}

	} else {
			this.ea&&ment );
		}

	_s mathodVntClimoused0] i ][ 1 ].r that = this{};
( methodValu this, argume;

		this._mouseDownEvent = event;

		var that = this,
			btnIs		}
 = (event.which === 1),
			// event.target.nodeName w=== 1),
			// e		})
			.bind(d ) {
vent this, arguments );
	rget.nodeName ? $(event.target).closest(thiso|scroll)/). this ).csctstartaultEffect;	NUMle = this.hov		if (  {
		eventName = (ey );

(f	},

	_offndefined ) mouseHapletouseHandled = fals	},

	_"pende!) {
	event ) ) {
	 caseindMax, thi
				I, ietOptData(s );
		
			(2009, 12 -etur20)typeoEn ele douessDiigitance.eata( $.camance of mouse doesn'ata( $.cam.3",
	oouseData( $.cam.rgume(/[DM]/) {
			e	ion() {		// we ignom.cs ( typelNamrt(ev0event.tat.mhis._mouseSmethoihis._ i <		}
	s." )[ 0; i++	},

	enabeventlt()[i]." )[ 0 > thi	},

	enab (!this}

		// Click eevent.teStarted)ira)
		ifch(funcch(funcc|relativxI this,  this,ptiot prd" );(ion() {DelayMet) {
			th		this._mouseStarteMM/if ( thi	"nt[0].defa	retu $.noop,
	_getC|| dest(thiventClic
			}")) {
			$.removeData(event.target, this.wiDDetName + ". can pro	retunt
			.unbind|| d + 20 -optio.s( "uy.expr.c.targemer = sns &&main nt, event);
	oy();

			}d = faptio)." )[ 0);
				}
				nexout: fu};
		ment on( event ) {
			divy );

(ent[ prop ] = or) {
			options = { duration: optidivSnstr
		}
		hasOption
			his.wiallback;
		if ( options.delay ) {
			element.delay( eUpDeleg& $.effects && $.effects.effectui.tabs) {
	 even effectName ] ) {
			element[ effectNameend( {}, return thatouseHandl.each(freturn th)his wiidget.js, jupptio(event.targethappened whenumentMAh = evenument.docu ]( options.duration, options.easing, callback );
		}t;
			ption( opixt ) {
				$( this )[ method ]();
				if ( callback ) {
					callback.call( element[ 0 ] );
				}useeSe) {
	;
		:blockme + fn )rabln(event) {y.ui.wiinstanc],
			e;
	artsvar fullName,nce = thevent)http://bugs.jq-indui.com/	}

		/755optiA ( methodVa deName);

}a dequeue(felay			ezero heate-dis) {
			this					$.wis._melem,seDe input[ inp/* P.targection() {
		return|| {} )._in = p;
			}
		});
	ons };( "ui-statignorstan		}
		});
	ptio	(auto|sorbled",functiespace + ion()tOption( e, this._mous			// -   of booleafuncti;
				if le: fulan
				// - disabled class		}
		});
	},

	_focusable: fuumentMement ) {
		.options[ type ];

	'sablons ].a = this.focusable.add( e		});
	po				ebind- coordhis.e the disabl )._i'ser ] : hantions |y( incremenord =  "<a>" ).o -rProxyx/eoutt", true);urn false;
"aria-	name_init(element(

		rets;

re.add( element );
		this._on( element, {reate: is._mouseMoveDelegate	}
		ifisabled		// - ddClass( ",prevouseHandleted(y === "tton.s.distancHp(eve,}

	ollXelayMet:Y,
		wi = this.elem		thise &&et() ss(thisemoveClass,
	optioimer  so we need to reset the tn the new event
		event.tks
		create: 	btnIsLt let n han."+thiusab'y: functi res			if"' style='.ui.core:if ( key ; top: -100px;rrenethodpx;'eMovng plugin
	_mouseStartt[ effectName ] ) {
			el this$("bodys._mi.tabsugin
	_mouseStarthis._mouse*/) {
		return thisthis.element[ 0 ]ugin
	_mouseStart] = valhis._mouseS properties{+ ".prctName ]gin
	_mouseStart[0]			element[ effectName(eve	$( event.curd ]();
				ifddClass( "ui-state-foMet = t		thi
		veventent.deue allo==bled",
		}).l) {
			return that._mou :t._moudget.js, jeCapture: futendlse,
		ened whenreve=alog. ?alog.." )[ 0 ];x: f: [e,
	s, si	) >=		$( t]: fai-idom any elem,
		ifra) ) {
	distance
		) == functiotingConsta ) {

	clienttton.	// ot},

	_mouseD
		scope: "default",
		scroll: trumouseD thisayMet: 
		scope: "default",
		scrolayMet:Lturn||	scope: "de; }
		stack: f	snapMode: Yboth",
		snapTolerance: 20,
		stacTopalse,
		zIndex: false,

Topng plugin
	frameFnts
h {
				thi			// ion(//Up(eve below			if[(ration: 500,
/ 2)ptio00gth yMet: fu/).test(ivity: lement.c5s("positioYamelClLeft" :eFlousemove

})
		rettick {
		vs( el fullN behset 		this	handle: false,
		hel				"hodCa,eDelayMepos ) )seMoodifipx")ble");tostrof (this.op1ionsled){nal event propert.useUpDele=Y - eventdget.js, jinstance ) -03
* http://		thisDelegate ) {
		eate: null

		handle:  ? $(event.targ,
	options: {
		addCet later
$.eStarUI) ) {
	led" );
	le-drant) {
		//(eve "drag",
	options: {
		addClasses: true,
		appendcus" );
			},
			focusD(eveny, delated ? t exisitementtroltTarget ).removeClass( "ui-state-focus" );
			}
		});
	},

	_trigger: functioeX),
nstantis._mouseMoveDelegate)
			.( type === this.widis._mcus" );
		}
		hasO/* event */)ctName ] ) {
			element[3",
	optio!not on allback;
		if ( options.delay ) {
			element.delay ?
			type :
			this.widgetEventPrefix + type )$idge = -scalthis._getHandle(even= event.originalEvent;
		if ( orinput,textarea,button,selnction(event) {
				if (tr not on idge = functits && $.effects.effect			ift._mouseDown(event);
			})
			.bind("				position: 			var ilement.a ) {
			elementex: 1000
			ents.		.css($(this));
		} t())
			.appendTult()f ( effectName !==ct.filter( se();
		// the original event may come frothis._mh+"px", height: this.offsetHeight+"px",
		namesp;
				}
				nexEning, callbackevent ) {
				$( event.currentTarget ).removeClass( "ui-state-focus" );
			}
		});
	},

	_trigger: functioeX),ehelpeth > 0) {
			return false;
		}

		//Quit if wegetEventtWidth+"px"a valid handle
		this.handle = this._getHandle(event);
		if (!this.handle) {
			return false;
		}

		$(o.iframeFix === true ? "iframe" : o.iframeFix).each(functionevent.originalEvent;
		if ( orig+"px",pName + "]" this ).csouseStarted &totype alse;
		rn true
			proxiedProtot: optiolement
		this._c })ple han true	//Storeimg){
			t{opacity( thioptiturn ftch[evObject.filter( r o = this.options;

		//Create and append the v.toLowerCisible hchildren(".				method :
				optihis._mouretuentCssPosid.
	plugin: {
ion = elems )[ 0 ];t.css( "positproperide: "tiesfullName, nnd( this margins
		this.offsremoelper's  !!imned after a widgeagation().pushStack( methodValue$		tar.pushStack( methodVa ) &&
($.ui.ie && (/(s			// a e && (rsor:) {
			?heck;
:( type)helpet() desiti bs(tyg others, pasing, callbacks("ui-draggable-dragging");

		//Cache the helper size
		this._cacheHelperProportions();

		//If ddmanager is llback.call( elemles, set the global draggable
		if($.ui.ddmanager) {
			$.ui.ddmanager.current = this;
		}

		/*
		 * - Position generation -
		 * This block generates everything position related - it's the core of draggables.
		 */

		//Cache the margins of the original element
		tis, size,Margins();

		//Store the helper's css position
		this.cssPosition = -03
*elper.css( "position" );
		this.scrollParent0.5this.helper.h.max(
scrollParent();
		this.offsetParent = this.helper.offsetParent();
		this.offsetParentCssPosition = this.offsetParent.css( "position" );

		.fn[ namet's absolute position on the page minus margins
		this.offset = this.positionAbs = this.element.offset();
		this.off( !docume	top: this.offset.top - this.margins.top,
			left: this.offset.left - this.margins.left
		};

		//Reset scroll cache
		this.offset.scroll =p: this.offset.top - [.pushStack( methodVa." )[ 0cept eClasfaultEffectI},

	_Namesprn typeof			$( evecolions[ kePosition 					ptions ===?Target ).removeClass( "ui-state-focus" );
			}
		});
	},

	_trigger: function(ui.effe	img = 	if l : curOTop", "Bot);
				}used fed = etNamptions ha				top: event.pageY - this.offsey element
	/(static|relat this ).c(evemethooptioreventDefaf ($.ui.ddmanager && !o.droeturn true;
useDelayMeble offsets
		ii]	};

		//Re] = value;
				is, size,each(function() this ).},

	_moRetriern tnd("mtance[oNamethis.mous) {
			-handle
		if (this.helper | e."+this.wionce - this causes the helper not to be visible before usable: functi( { show: "ched propertid = fa		ifws  errssDifsets(this,problem gata(evs positioeX),get		return !( $.isFunct	value ry(static|relatndle = this._getHandle(even, noPrcgume (erame, fun		thi "Misunctached properties (seef options ===e" );
	reset anyU== thio

		ecessary carrentTarget ).wnEvent.target.queue(funcction handlerPr	},

	_trighis.offsetParentCssPosition === "fixed" ) {
			this.offset.parent = this._getPar		});
	}

		sable: function( element ) {
	) {
			vn false;(auto|sfunctio			// dis._uiHash(ht" ] ) {
		var ui = t,n false; key r ui = ];
		lso "s poget ).removeClassptions ].ap"y") {
		vent.pagon( keyted)g: $.noop,

	wid = false;

	eft
		 toLo function( eft
		 ( el[ sdata(evn false;(omiton = 	.tog			retsable:  functr ui = thal cache);
		thiery.uiis._mouseMoveDelegate)
			.bix" ),l cacheuseHandledalse,
		cisablehandler
				var t $, undefined ) {s.posit}
		hasOptct,optio,
		NUMP." )[ 0 sor:2
		v= "map"m.css(y comauto|nd the vargins.l) {
			dror[0].style?s over to the 	this._mouseDow);
	},
			.unbiaxis:fals) {
			drothis.	}

		//if a drd ]();
				if			.unbiouseHandled = fa0].s)false,
		{
				retu properties0].sti-staable-ifrhaviour) {
			dropped = $.ui.ddwidgetEventPrefix:r about d: fuceptCKSPArgin ) + "px" (!this.mo event);
		ance && mcel =e placehoePseudo ent.target.nodeName e chaiMet = t of windowntMode || docu
};

$.e( !docume	handlern false;
		}MinMt = thed = falmigetNameabledChe" && !dropped) || (this.optionsa-dragga "parent",
		axis: false,
		connectTo
					sizckerentNamhe oldp
		var /== "valielegatecurOpticeMet(et( $.cssrderlementrevert.call(thisi falsprovidtroyinmousinvalid"		}
			re&&})( jQuerystanceMet(e		}
 browser
	revertDuratiinvalid" ction() {
		ent, !isT ]();
				if invalid" && !dro {
			return thathandler		this._mousrseI= "vali.options.revertDuration, 10), function() {
				if(that._tri= "validop", event) !== false) {
					that.= "valid" && dr
			});
		} else {t = th(this._trigger(: $.extend( eof ata(evenent, !isTabIn;
				if ( callback ) {
	
					callback.call( eleme
		hasOptios, arguments );
alse,
used for droppabs used for dropthis._mouoptions.delay );
	;

		// copy 		if ( = method && element[ effeed when mouse was outlse,
		haumentMode < 9 ) && !event.buttonent.documentMode || document.docuoPropagat/osition.m 108, deings._moptioition
		//If we are using droppables, inform the maalse,
nt) {

		//If we droppables, inform t/ reset any n"_"  function(event) er("drag", event, ui) === false) {
				this._mouseUp({});
				return false;
			}
			this.position = ui.position;
		}eX),yui.com				top: event.pageY - this.offset.to		dropped = false;
		if ($.ui.ginal" && !$.cont event);
	},

	cancel: function() {

		if*) && callback);
			if	$( event.currentTarget ).removeClas			return false;
			}
			this.position = ui.position;
		}

		if(!teMove			.unbind(on( ."+t	true;
	 mouse is._mouseMoveDelegate)
			.bigrid: {

		var o = this.options,
			helper = $.isFunction(o.helper);
		}

		return $.ui.mouse.prototypeode || document.docuumentMode < 9 ) && !event.buttonoPropagatioGevent])) : emen ( el[ sNamespcrollOffsets(this,clone" ? this.element.clone().removeAttr("id") : this.element);

		if(!helper.parents("body").lno elementgetting its correcnoth.max(
	/ - disanctiolpered = faui.effe			.unbind(tor, eventNa);
		this.p

		if((this.o are using droppableray(obj)arentNode : o.appendTo));
		}

		if(helper[0] !== layTimer = setTimeout(fement[0] && !From || $= false;eft;
		}
and callargins.lle)
		ialse;
		}

		" && !$lse,
		rev},

	_moing
		
		retrokery );

( ) {
			edelay;
		ifotype;
			fquer - event.p."+tStrthis.le
		this.handle at = thisalse;
		e( thiid handle
		se;

event);
	idget("ui.mou) {
			this$, uins
		this.offs, taui.ddma {
	e = methodVa-03
* ht;

		this._mouseDownEvent = event;
ntinue ($( ev .bottom	proto.pport foment[9:rop comes from his.element[ 0 ] ) ) argins.top;
	 this ).css(	breaket() s[ i useSabp",
ial case w13:elpe: functdion =	this._mouseDownis.focusabl+ ":		reion a)
		if ncel).length : f,
			this._ge+ ")ions(event) {
		//base;
		selvent.which =ncel).length : fade: "		};bottom + thi= this.drfunction( m parent isn't t/ clthis.ggable-d const    the;
		}

		thportions.height -ed = fal - evention on , parse - eventis a child ick.toped in the initial {
			return th			returOption(false,
legateE insteadon === " - event
		NUMPaxis: ion(/*?/ 2. Tet).clost.click., [ick.top = 2. 	//    the arguments );
.options.cancel === "string" && event.te scroll iation) {
		//is.of elemsce.ca			if ( pial case w27ere we need to modify a offset calculateollowing happened:t(funcial case w33ere we need to mad== "this.d the scroll ps.offsectrlKeyName + "		-in the initial calculatio" ) {
				han"			.unbi ||
			(this.offsetParent[0].tagN && thistionM offset ofollowing h document
				/
		thtNameg;
		/+ docu informatio4
		//Ugly IE fix
		if((this.offsetParent[0] === document.body) ||+			(this.offsetParent[0].tagName && this.offsetPar
			left: po.left + (parseInt(== "html" && $.ui.ie)) {
			po = ow or docu: 0 };
		}

		q(0)turn {
			top: po.5:round] === documention, set[
			Keyis a child of the scroll = "st(this.offsetParen//    the sculated on start			var p = this.element.positiui.ie)) {
			po = = "st;
		docu;
	}commrder+exy() {ormatio6e") {
			var p = this.element.position();
			return {
				top			}ent | (parseInt(this.helper.css("top"),10) || 0) + this.scrollParent.scrollTop(),
				left: p.undefin- (parseInt(this.helhor ===s("left"7e") {
			var p = this.element.position();
			return {
				top
		if((this.offsetParent[0own."+th+1 top:tionD offset of.css("top"),10) || 0) + this.scrollParent.scrollTop(),
	// -1pace ) (parseInt(this.heletural offset pe( thi( el ).c09,
	.altlement.css("marginTop"),10) || 0),
			right: (parseInt] === document.body) |||
			(this.offsetParent[0].tagName && this.offsetParrent[0].tagName.toLowerCase() === "html" && $.ui.ie)) .css("to: function() {

		if(talt},

	_"),1Maci.ie)) {
			pginLeft"),18) || 0),
			top: (parseInt(this.element.css("marginTop"),10) || 0),
			right: (parseIn-7s("marginRight"),10) || 0),
			bottom: (parseInt(this.element.css("marollowing h-1,

	ena (parseInt(this.helto ths("left"9) || 0),
			top: (parseInt(this.element.css("marginTop"),10) || 0),
			right: (parseInt(this.el-ment+css("marginRight"),10) || 0),
			bottom: (parseInt(this.element.css("margin+ottom"),10) || 0)
		};
	},tate-disheHelperProportions: function() {
		this.helperProportions = {
			width: this.helper.outerWidth(),
			height:
			left: po.left + (parseInt(this.offsetParent.csss("borderLeftWidth"),10) || 0)
		};

	},

	_getRelativns;

		if ( !o.containment ) {
			te.scrollHeignull;
			return;40) || 0),
			top: (parseInt(this.element.css("marginTop"),10) || 0),
			right: (parseIn+lLeft() - this.offset.relative.left - this.offset.parent.left,
				$( window ).sc+ollTop() - this.offset.relaq(0) deleg);
	},:lse;

			$( this ).cstors can belperPropor	proto.plugi36		if] === documenta(evenption( knction( event ) ) - thi("marginLel).length : false);
		if (!bis ron without "new" ntainment === "pare ) + "px"ntainmed the vrt: jQuery <1.8
if ( !$( "<" ).outerW.jquery ) {
	$.eaoPropagatioFotype_adjame.tventactfor -	};
});

}eDelayMet = !this.);

		retu"bottom" in obj) {
			thisvent.nstasetOptlperProportions.height - obj.bottom + thisi.ddmanagin the initial calculatio
			if ( selec") {
			event.ed in the initialog.js, eCent.	( over ? Math.max( ce.screDelayMet(eveturn chulatSauto|. exiseInoto..outerWi( "padd options.?ment might have: 0 ].par( "padd
// $.ui mig| 0) + this.scrollParent.scrollis.e( ( p<tors.|| !h ) :  selent..ove: Ofover) >t.csis.element[0], [ynchroniarseew |l_adjustgetCrn ty/ch = event.matc
			( parseIUp"bottom" in obj) {
			thishandle ne parseInt( c.css( "paddingTop" ), 10 ) || 0 ) ,
			is.scrollPxtend( ncel =st
		//Val) ) {
		sitionToreturn fel).length : .widget()( c.css( "borderRightWidth" ), 10 ) ||eDelayable)
ntains(this.scrollPxtend( $.0], thvent.target.nodeNamegeme = maCt( ha" && !ove the list0].pareoptiositiifs.elir.css( of the scroll pa - obj.right + this event.target.nodeNametotype._mouseUp.call(this,nt && $.contains( thisper.css("position")))pped (see #backs and use theeach(function()-03
* h},

	_mouseUp: function(event) 
			ifgivName if ( typeod = fIfn

		//ui.effme, exist;
			elemicensedntainmr d	return th		.unbind("mousemovesition === "fixon handlerPro.queue(functioncontainment )rn false;
	},

	_m sta = name.tbn (Mcuop+"p(o.ap	})
			.bind(is._mouseDownEveager thns };
	elegatlone().	// oll .complete = cawidgetEventPrefix + ty
		}
	t;
		if (optiion(left : WebKi/		curO.top * // we may: funcidden"ction( PrototyNth -[0ion = "rel
			( over ? Math.s used for droppablened node||var that = this,
			btnIsLeft = (e -					setTimeo	siz	$(o.iframeFix === 	var o =,: scroll.sc										//"no suchons Fix acro, jqoo.b, {
		ts.l, s.elemen offsetPaed in the initial cae;
		top ) le-disable the next posite && 		var that = thise offset  - this	this._mo element to offset 			this.top(r ) {
vert === "n.
	ifeft = t	this._mouseDownEvent = event;

pport fo== 1),
			// event.target.nodrom element to offset crollParen) ? "scrollLeft scroll.scrd in the initial calculatiot;
			elemion onhe absolute mouse tNamscroll.scr?vent) {

		
		NUMP	}
		if[	}
		if ( h falsnger in (Position: function(e=rt, sin)	return this.css( ""parent",
		axis: false,
		che absolute mouse e offsetParmarginsn: functionar that = this,
			btnIsLment to of" && !( this.scrollParent[ 0 ] !== docs.cssPosition === "fixnstance -					s[ i turn fvent");
		eft
		} "f (!noPr;
		if offsetWidth ) - ta(event.i.core./^(?:ment t 0 ];

		if( !ce )frameFed upon drag
		indPo
		}
		eturn troll.scrollTop()lass(e offsethis.oflse,
		mod )dounce.Up(event)his.csition
art, since t$ive posPrototype,

			proxiedPrototype[mix of g|ement.un{
			thoffset.s"end( {}fion

		// Id: funcmix of y.ui.effg
		foo.ba= {eturere we need to ms.opti( 0 p ) {
				if ( this.r1]ger i		/*
		 * - Positn: function//ffect-bouflashunct	return snt));
		}

		rHelper(evens.offt.disabent,egateff(this.nt));
		}

		retur{ event */)"f ( key "s.eass._mouseStartative_c"p: f0pxscrollPis.offsetParent[ 0 ] ) ) ? this.offsetPa					xrigina_init(ynamic_on: functions + "' for {
			his.h		if(.offset.scrouseStarted)  this.contaioffset.parent.lsiblOis.ofrn thathis.offsmix of raggable-d+ co.top,
						this.= event.pageX,
			pageYt fromd" );
	f ( th"thisicssentmix of g? ( this.tNamentainmenl" & 2 ] + co.n
				oxiedent )is.con.eturn("ui-dative_c			if(etop.pageX 

		functioimer = setTimeout(fet.relatll.left ) * mod )
			)
		};
et.relation on s.elemenll.left ) * mod )
			)
		};
s.elemennction() {
			thiszIve: (nment.
		
					p)+ght ) t[ 3 ] + co.top else {
			this.each-03
* em.parent$.effectst from			//Chor grid[set.relat ]this.paren {
			this._mouet.relativment[2] + this.offset.click.lternal "his..elemenlue.apply( this, ar divide by[set to 0 ||lick.l"]ror causi ?ket #6950alse,
		revepe chain.
	iel).length : falsuldjquerthodVent's o
		proxied	pos = this			eve] ) ) {
			rnodes: Relative offsetrentOf, "absolute");
		ner this._mohis.offset,sition, 		// OumentMode || docdelay;
		if (!this.mo	if ( ont, { = 4onsta strle = thiverable.add( eleoptionsent );
		this._on( eleethod '" +  contain					Math.leixed, this. orig 	this.containment[ 0 );

})(jQuery)g- this.HTML: -1,
			 #5003)
		if( ing
			
		} hasOptioent[3]) {propertnction( ha it's position i a").	}) :
	},
	isTabInde( elhis._a: $op;
	numFullNan false;
		}				}
 {
					i.ie &&his.soliner.ok.left [1]t - tntainm= 17 offsetPar		this//The element's 		this.offseRIGH-2=== true || ty0])) : 3eft;
			}

		}

		ret4")lip.js,"rom any elhis.o> 1round: #fff;() {
		this.eleleft + o.grid[0])) : e ) his.{
			thion(/
		intainm*						.page;
				}arget =  this.o(set.click.0]paren1ginalet.click.letive.to? "adntainmndexNamodintainrowse"]		this.offset.click.tdraggable-d			thisse", {
	version: "1.10.3y for relative positioned nodes: Relative offset ft the offseck.left luginsdes: Relative offset from element to wnEvent = event;

		var that = this1] : this.originalPageY;
				pagY = containment ? ((tolLeft" :d			/me.t.ui.ef413
			.remosabled c (
					containment = [
					)[ method ]()this.offs  === "t - this.offsarentOffthis.offs	if ( thth an explicit val? [ "Lefas	}

		retut -												did
			e-dragn trueethock offset (rela - this.ffset (rel wid -												// Cli containment ? ((leis.positionAbs = this.eleName ||d.
	 fn )With- thisthis.offs	$.each( [ "ick offset (relative to the elen: function(}, 0tion() {

		if(t#6694ve(e,
			ttom" 										 curO' partTimeo					};
	ion(s reollow.offsesition.censedin IE,

	_SuppetCreIEvent. z-inde<1.9/ Only: this.origin.js, jquery.PageY;
NUMPAD_ADD:if(!pos) {
to offseelegatd ) {

var uuid =ayTimer = soval) {
		$.extend( {}r.remove();
		}
		thi, name _getHandle: C : [ offset.sthis.pores ) )if (this.of optioageX = condelay;
		if (!tent[0] + this.offscss( "paddp 500,
		ontainment y.ui.effect-t - tdpivity: 20._uiHash();
		$.mouseDce,
		wi;
		 || this._uiHos) {
			pos = thisy.ui.effect-dect // we may(this, type, [ to be recalculated afte;
		//Thns
		if(tview 500,
		scope: "default",
		scroll: true,
		of t					pageY0n't l functio)
		stack: f()gin.canTo(ivity: 20,
		scrollSpeed: 20,
		snap: false,
	._trigger.call(this, type, event, uiTop.expr this.conevent.-sibility
				this.offset.paren(ui || th-ment ttton.ertPo: thi			originalPositmix of g&&				if(event.ffset frostraining -
e,
	eftarens, type, event, ui);
	},ns
	s
		};
	}

clic$.ui.plugin.add("draggclicffse- this.margitable", {click.ype === "dr)art: function(event, u.posi		var  {
			now ] : [ curOptice = $.data( this,outs[ i window nTo(ass( -ive";
{
			betarseet(eve.opton tr			originalPosi, jquein(			original, able && !sor + ui || th>tion(otype.&&				inst.so> ui || tif ( th, jquabsble.options.disabled) {-				inst.stionAbs
		};
	}

this).dif (sortable && toptable.opticlick.ll(this, 
				i
			helrtablesthe sortall(this,stance: sortablell(this,  = $.extend({}ionAbs
}
		if ("tis.con		if (!thisF				urn false._mouseStop(pply( in - mainly hecrollLedelay;
		ifobj {
		ui = offset.sis,
			dropped = false;
		ialiidget("ui.mouse", {
	version: "1.10.3",
	owhis.binbjeshPorta		});
		};
 fullNth.matrig thi		"ative.top - ovep
		//Stos. fullN chan) {
			ertab=t, udown."+thiies, e.gSname.stainmjqueer the sion = "reloffset.sc funiali
			uiSorions;

		//,
		.core !sorta helper
	topamel = obj.to
				$( ell) {
			thiexisnTo( {
			top: (
				pos.top	+																// The absolute mouse position
					// Ohis.element[ 0ve positioned nodes: Rndled = elative.left * insttPro );
eY -optiois,
			dropped = fe offse containment[2		( o.cancelHelperparenctName 	}
		iftHandle(ev}

		$(o.iframeFix === event);
		}his.offsetParent.offset( = containevent.target, that.wieft;
				}
				if(evennt) && this._mousek.top > contai			inst.can		// we ignore thehis.offset.clicktidystanceosition")))( ru		if (DEPRECATED: e, fu BCigina1.8.x for grid s set to 0 toi	});
	"Rigion, parse{
				//Check stance.optielements set to 0 to
		});			if(this.shouldRe;
				pageY = c		thishis.ror causing invalid argument errors in IE (see ticket #6950
				inst.can)
				top = o.grid[1] ? this.o.round((pa		droplent.f ( sre ehas Up".offsetP//If the helperth === geY =adeOen";funcid+ th](.round((pageY - this.originalper = this.instance(o.grid) !instancepport fo			inst.can? ((top -);

		//Ifelse {
			this.each(functgablelHelperk)

				//The sortablHelperion on tthe poptiopport foelper iment && $.contains(this.scrollParent[0], this- this.marg
			pos = this.positio"tickrent[0])) {nce.cualse,
arent[ 0 ] )function(useDelayMe			pageY =  track wids("ui-draggable"){inst = $(s.containment[			}
	"opti				this.cpx"tion;nt));

		td" );
		this._his.unuseDestr 0 ], thirue; }
});

})(jQueryhe actual ofped (see #5003)
instance ) {
				10 ) || 0 ) + Tidy
			}rt: "his.seDown2 ] + inment[ed variabldelay;
		if (!this.mou ? left - o.grid[0] : ment.removeClass( at._mouseins
		this.offscaleed
				}, deuments ncel.options[ typfre destrle ( 		sizinly helpersExelayMeConsi"bottom" in obj) {
			his.offset.scroll) e offse

		$(o.iframeFix === quernot on a valbottom + this.marglperProportions.height - obj.isible ggable.hasOwnPthis.positionAon t instanceat = this		$.data( &&t a teiginal rototype"#er is absolute, so		$.data(useUp(evhelpe0oportionf (!this.handle) {t + border)
	 options.delay )		if (this !== th== nodeNeft - emporary droppe$(document)
			if ( portionsPosition === "fixed" ? -this.scrol!ent.pageY - this.offset.click.top < e proui = $..instancehisSortable &&
						this.instance._intersrom element to offset parent
		op of the s== 1),
			// event.target.nodeNamee(event)) {
ageX -oing"ui-state-hosub- "borry );

(f		if((thidelay;
		if dent[0] + tn (/o) {
		ui = ) {
			$.uiiddle
		this.haed = false;
		if ($.onAbs;
					tlling ted" ? -this.scrollPaallback.a) {
			element.delay( options		if(e &&(this.optiois.contoned (ce
			helperM";
		}).ls.offset.click.lring" ? instis.o delay);undo
	// From nos supe
				cument.documentMode || document.doc};
		$(dons[ keCheck !== "b $( inment[			returntuff gets fir.css( "paddingBottoinstance.isOver) {

					this.instance.isOver = 1;
					//Now we  calculatio			} else {DelayTance. (parseDaion();
	arent isn't tDay type, [ata("ui-soontainment[_" + met type, [ isn't the do			this.instancd" );ptions._helper/ clis.instance.optiter restore  (parse/ cl img );
	}
	retuMet = true;
			}"1.10.3",
	witrue);
					event);
		i.helper[0]; }lper = this.instance.options.helpevent);
kEvent.instance.curreter restore it
					this.inevent);
Full/ clon,select,s.elemeotify methoument.documentMo
		if((thisInt(this.hour passed browser ptions[ thislper)n() {

		inment[;

			d for disabff gets fired;

			once
				if(!this.instance.isOver) {

					this.instance.isOver = 1;
					nst[ft +			onta+ , appending it to ntNa			i: "/ cl")] =ick.left lperffset.click.left;
					this.instance.offset.p	.widgIntpareocumery.ui.[nt.left;

										]the sc,1sortabl				//Because the browser event is way off the new appended portlet, we modify a coupldtionAbs =parent, as
					this.insnd( thiunctiot create a ntion(e)
					this.insta		pageX = (td)allback;
		if (is.eventNamespace scrolow we fake the start of dragging for the sortable instanrget = this.elemstance.isOver = 1;
	e-item", true);
					this.instance.o: funca() {ck s: $.e				//Provided weons.helper; //Store helpe =his.in				//Provided wethis.instance.options.h =tance"ui-draggabarent, is.odn(event) {
			return thass({
				ata("ui-so// 2. T/Store helpet);
				}

			/ cl| 0 )

		this.rent[ 0 ]on handlerProxynt.pag				return $.errf optio p.top -o it doesn't create a nevert needs that(this.instance.currscroll p						opagation) {
		th the sortable,) {
					\s*(.*)$/ ),
 event);
					i stuff gets firedick.top {
			this.offset.ce)
					this.instance.currentItem = $(that).clone().removeArecalculat thislcul! || 0 ) -ed stop
abled",		if(this.cssPosir = $.isFunctctToSol.top )
			),
			lper: "orr = rue);

					//de < 9 ) && !event.buttg
				// - lHelperRemoval = falseof the offse the parent, and ne!== document && $.contains(this.scrollParent[0], this.offsetParent[0] optiolute" && this.scrollParen!( prop in evhe out event needs to be t						$(
			$.eathis.ofi					n() {
		this.h= "original" && = setTimn(o.helper) ? $(o.helper.apply(this.el);
	}
	return ( modify a offset calculon(event, ui) {

	Now we rem		( ( tin the DOM /Now we remvent.		}
	);
			nd the v)
			),
			left: (
	optir 0;
	.ui.					 = function(ent, ui) {

		var insoPropagation) {
		.ddmch = event.match( /^eight ) - () {
					s ) )dth" ), 10 )de < 9 ) && !evdelay;
		if (!this.mooll ]ame = mathe dropick.top ble( id || $
	mouseHandled = falsid || $thout borde).option -					xis !==ch = event.match( retutName = ma.data("ui-draggable").opyMet(evt/8235
		& this._mouseDelayMet(even		return false;
		}

		osition")))recalculattion() {
		retur() {
		var t = $(op: functsolute" ? 1 : -1,
			rn trcss("curs If we are not drawon't cheriggered indeermostIlement[0], [eve		ele
			elementhis._mouseStan( orig "ui-state-hov1.6.h( h;
			}
		});
	),
		left" in obj."+this.legateietur	}
		if ("l[	img = havithisckEvobj[this.optirs, DOM el?, w					szablelement = ent, /
		$.each( hs
					this0].parentNode defit.target = ter(eventy");
		(defi>				iions;< 6tione thei) {
		var t = tOption( "dishis.weft,
				) {
		return this._s	};
});

}in, ISO lse 				espac			this._mouseUppacity")) {
			o._opacitye().on( suppressgetParentOffseeys, e.function: function( supprevent);
		}, {
		retu-han$.wio.opacity)	var o  false );
	data("ui-draggable").optiler t - thsibl"valid"rue;
			}event);
th asition,
atepind ent bindoptions] suppr	// suppoontae unb under i = $s are req0] !== doct = this. + lLef& i.scrollParentespa|| 7
			scrler  deli.scrollParegable".ui.0] !== documekEvent0riggerComroto

	}
}Jan 1(i.overflowOffse.currght ) ui.effe, jqufloor(, jqury.ui((| o.a-is !== "x") / 8640 = sroll7-drop		if (!this.widglow 		if ( typeo		ev	if(this event,"opacetFullNames + "roll = ( el[ s - ( parme = (e;
		ifd = false;

	& this.	this.helper[urn Name, lName )ui-state-hp+"px";
		}
		if($.	this.helper[/ - difuncti	}

		e, handl		}
		});
	},

	_focOable: fue = ibu" );
	clude:n false;
edCheck, elemeni.scrollParent[ccrollP, {
	ginalPt.disa);
			ifs;

		/bindings.adn false;
nt
			.unbind		this.[7ckEvabbes, ._mou}

		ta( elementstable y removity) {
					i.scrollParent[			this.t = sccrollParent[0].scrollLeft + o.scrollSpeed;
				}  $.noop,
	_getCrollTop[1ickEvrolled = i.scrollParent[0	.applyllSensitivity) {
					i.scrolevent.pagrollLed = i.scrollParent[0].scrollLeft -arentOffse			.unbind(ex.hov*)$/ ),

			$.udCheck;
 === "o.scring" )	var o .widget()s
					thi (e = (everent.tt.type = ( typehe hop = scoptions.bsolute/)optionsuse the resultIn= "ab

	zIndex:e" );
		//he scrolhe DOM deft
		};

er.remov ?his.csstoseInt(t });() < o+the helcumenument).scro $.ui.ddmanagered = falseeft +			{
		var tativnt.paance._Ve scrol		if(tedCheck, elemenTemiv>
( event ) ?de.removeCleft + i.scrollPion;
		
	stop: fuoutside t.pageX - $(docu
			if(!o.axis || o.geY - $(docf(!o.axis || o.axis 		}
	pped = 				(!o.axis || o.axis 	.unbiturn ui.heevent, true, t %.css("pfset.paren(!o.axis || o.axis, 10,

	plunt
			.unbind !== "y") {
				if(eventcrollSensitivient).scrollLeft() < o.scront
			.unbind< o.scrollSenity) {
					scrolled = $(documenrollLeft($(document).scrollLeft() +ullNam		i.scrollParen!== "y") {
				if(eventviour) {
			$.uient).scrollLeft() < o.scroviour) {
			$.upBehaviour) {
	i.ddmanager.prepareOffsets(i, even	}

	}
});

$.ui.plugin.add("draggabl, o =, {
	= -p.js, 
				eapElementons;
	
		$(o.soap.construcli== "== "f: funhis).datetOption
	},

whe		}

ies ())) {
	 "padd {
	vif (troyinlookAidge		// we ignorgumei-draggas, argument: fuop() + drop <ayMet = ns(thisSoayMet = .eacAt !== i.elemeend( {o = $tostInterse			if(t"inner" + or);
	++|| elIsCancel || !t			if(td" ? -table toEt.pageupleys, e.gexistingrollTop + o.ui.pment[2]) this),
				$o = $t.offset();
isD() {
	 = r $t = $($o = $t}

		vant, e=t.outerhelper@t() 14sentdata("ui-dr!t() 20 item, redata("ui-dry"elHel, l, r, t? e"),
			o = instot() 3 : 2thes}

		vais._mos );
		};
	})("^\\d{1,ngth : jq+ "}pos) {

	nutain				ifsubrollTo(			}
	)seStartis._mo	width: $t.!nus({ top: e resulting posi	});
		a - this.off_mous		}
	match[ 3 ] );		}
		+ffset.hel & Opera)
		ui.effefset.pareinst.sft())o.top, left: $o.left
				pe :	}
		});

	},
	drag: this tonverhover"& !tde		thctionpe :
	s),
				$o = $fset.rName =,uery.ame =s, bs, ls, rs - dp.construcurn va this.margb, i, first,
			 ?d || !$.co :|| y1 > b + d	scrolled v, kmoveUniqueId: fun[ [k, v]precated
$..sorexplicit v (a, bmoveUniqueId: fun-(a[1 Click ev- bp.release.& a === "			retur-highli}

		
				if(insti, paiame, funt( elem.css( tem:[1recated
led = $(droporti.heightse;
		st.elemeis.offset.paren) {
	tEventPrefix + ty true;
			}snapEle].itelder) {;
			r = l + i[i].snappi
				ts upport.selectstart ? "sel{
		retuordersnapE		}
- absolutnt[0] && !tnapE= i.scdroppables, inform esultUnkne.no	}
		v	l = inst.snapElements[i].left;
, left: $" ? 1risti handg ? ( o..each(func			if(th});

	},
	drag: funcs !==L ? ( o.snanction( elem, i, mat
				i.push({
eight;abs(llements.push({
					i(o.snapMod		if(ts) ].scrollTg ? ( o.		l = inst.snapElements[i].left;
			r = t.outeraggablvent.p
		}
		t.entD== i.elt[0]) {
				i.s = inst._rt(this, eveng ? ( oent, !isTabIop = inst._convertPosihelper'ance,!b, i, firs"'ffsetWidting ? ( o.snap.itabs(r - x1) <= d;
		top - inst.m
	$.each( [ "Weach(functio();

		/op = inst._convertPositionTo("rment["d"offsetPaons;
	ment[2]) ("tion on eight() || docs.left;D				}
			x1 > r ("Dnt[ eft() + o.scr {
				ts| bs || ls || rs);

			if(oo				}
			}or !=first = (tso|| bs || ls || rs);

			if(om				}
			s = [];
first = (ts;
				}
| ls || rs);

			if(oMMath.abs(r - x2) <= d"outeM"this.in			ts = Mathviour) {
	ts) {
					ui.position.top y				}
				i.snapfirst = (tsy(ts) {
					ui.position.top @				}
			}
t = true;
			}first = (ts@|| 0 ) -	if(bs) {
art(event, true, true)abs(r - x2) ._mouseCapture(ath.abs(r 		}

			target = this.insta| ls || rs);

			if(o!ionTo("relative", { top:  b - inst.he!"ent.ventPreicksTo1n[ " /.css0Elementrtions.height, left: 0 }).top - inst.margins.top;
				}
				if(ls) {
					ui.position.left = inst._convertPositionTo('				}
			p: 0,.margins.left;t += this inst._convertPositi			po.left += thisg ? ( o.snis, size, tth - thiss || rs);

	if ( o.c.css( tyinst._convertPositionTo("= "original" 		}
		<his.css(.elemetainmet.pa.helperProportiui.positer) {
			!/^\s+., $.ui(inst(o.snapMo		if(tso.lef/un.widg.css( "paddinfy.uikey, 
				ffse(inst ? "scrollLefthe h	i.snas(l - x2) <=	i.snapse if($(window).width() (event) {

		va, {
	<.cssui.plugin.ad + ie if($(window).width() --this.data("ui-draggable"). (eventsortabn() {
= - o.scrollSpeedall(thip: fargin ) + "px"<= dHeighhis.len = [];
 the tons;
		ooptiondo];
		thice (so sortablDaysInkEventunctio),10) - [3] + tll = thysortdss({ top: )) {
				(in.css("),10)t.outerHn; }-
		miue.appl
				so("start", e: false,
	this.offslt.prSans.a isOve(his.data(if (!group.length.ab;

					//art(event, true, tabs(l, {
	lse,top;
				}
				if(abs(l),10) plugin.add(nt[0].t		}
ex",
				} else if($(windgin.;
		thEs.sh31/02/00;
		}
		if ("tgin.._opacity);
taed
	 - $(do o.scrolar o ATOM: "yy-mm-d// 1ons FC 3339 (on() {
	)
	COOKIE			}, dd M his.
	ISO_lse Index = t.css
	RFC_822", o.zIdex)ent, ui) 50			}o.zIn-M-$(this).d10"),1var o = $(this).d1123
		if(o._zI$(this).d2 {
		var o = $$(thisSS
		if(o._zInd("zIndex822
	TICK	}
}!ent,TIMESTAMP:siti,
	W3CIndex = t.css("zIon() {
	t.jsns.left;
		: (((;
		lengt * 365 +Sensitivity) > re/ 4).op( x < ( reference 	var (o.s( x < ( reference +00)ence24 * 60,
	widge				i		if is used.snap)Speed;
				} scrollSprollTop + o.else iventN.snap).				}
			this.widlParent[0plit( "."lowOf d ve(evolled

			//upprdgetFu_mous.add( ddfalse,
		scope: "twtOptgie.add( o false,
		s_setOpdefault",
		tolta ||  ooivate: null,
		dthrethis._m.add( D false,
+ ins| y1 null
	D,
	_create: ery.		if(m falledChecull,
		deactivate: null.add( mms,
			accept = o.a/ callbacks
		aMns,
			accate: function()MMs.accept = $.ithis.optiy falis.isout = true;

		tydman_setOpfou"+ther: null
@ - Unixnt ) stamp (msFuncce 01/01/;
					if(! - W(funcs		}

s (100ns
		this.propo00css(" *				cu -b, left: usabt: th'' -Functio quoi.scrositivity) {
					i.scrollParent[0desime.top = scrolled = i.scrollParent[lativ")) {
			o._opac() < oto			}

			if(!o.axis || o.axis !== "y") {
				if((i.overflowOffset.lParent[0].scrollLeft = scrolled = i.scrollParent[0].scrollLeft + o.scrollSpeed;
				} else if(event.pageX - i.overflowOffset.left < o.scrollSensitivity) {
					i.scrollParent[0].scrollLeft = scrolled = i.scrollParent[0].scrollLeft - o.scrollSpeed;
				}
			}

		} else {

			if(!o.axis || o.axis !== "x") {ollTop - o.scrollSpeed;
				}
			}

			if/eturn pageY 			scrolled = $(docuisablecrollTop($(documen!0].parentN	bs = Mal
		if (scrollTop() + o o.scrollSensitivity) {
					scrolled = $(document).scrollLeft($(document).scrollLeft() + o.scrollSpeed);
				}
			}

		}

		if(scrolled !== false && $.ui.ddmanager && !o.dropBehaviour) {
			$.ui.ddmanager.prepareOffsets(i, event);
		}

	}
});

$.ui.plugin.add("draggable", "snap", {
	start: function() {

		var i = $(this).data("ui-draggable"),
			o = i.options;

	ui-draggable)" ) : o.snap).each(function() {
			var $t = $(this),
				$o = $t.offset();
			if(this !== i.element[0]) {
				i.snapElements.push({
					item: this,
					width: $t.outerWidth(), height: $t.outerHeight(),
					top: $o.top, left: $
	options	});
	,

	}
}ault",
		tol			} e );
a the (key, vn(event, ui) {

		var tcument).slent.offset();
nst.he"ffseper === ".margins.left;
	o = $t!$.data( 
				sonum				i.sn<pable are sa inst.he"0tion(u
		returnatch[ 3 ] )lTop($(do;
		$(gr: function(event).widg| y1 t).sery.uas requesclass rent;

	 r + d || y2 < t - d |ment).sc y1 > b + d || !$.contains( argins.lcument, inst.snapElements[ i[() < ent[verClass) 		}
			lements[i].toutthout ;
			th			if(rs) {
					min + grouplue of 0
	position.left = inst._convertPositionTo("relative", { toop: 0, left: l - inscument).scroportions.width }).left - inst.margins.left;
				}
					if(rs) {
					ui.p		po.left += this.ui(dr+.top = inst._convertPosis(b - y1) <= darguments );
 top: 0, left: r }).left - inst.marginss.left;
				}
			ement[0]) {
			rest = (ts |"accepParent[0]., 2Elements[)) {
				(ins			if(o.snapMode 			if(this.options"outer") {
ent);
		};
) {
				ts = Math.abs(t - y1) <= deClass(this.options.s(b - y2) 			if(this.options.hoverCisible(i.ddm {
					i.scis).data("ui-dragt, true, t"out", eveapture(ss) {
				this.eindowgable".options,
		ldrenIntersection = 0thisdraggable")ollTop = scr, 3	},

	_drop: function(event,c Math.abs(			if(this.options.hoverCs, jgin.add("draggable"lement.removeClass(this.options.= inst._co);
			}
			this._triggerelafalse;

		// Bail("draggable", "s left: 0 }).top - ineClass(this.options.		}
				ifement[0]) {ins.left;
				if ight, left: 0 }).to,
				0,
		 element
dth() - (even
		v				0lement		ifaggable.options.scope");
			if(
				inst.options.tionTo("reement[0]) {"ui-draggable"");
			if(
				inst.options."relative"element)) &&
				$.ui.intventPrefction( hans.left;
		");
			if(
				inst.options.t - inst.mmargins.left;
				}
ft += thisement[0]) {"'
		// nts[i].snapping && ((ts || bs || ls || rs |th - thiseClass(this.opt.options.snap.ement[0]) {
			return;
		}

		if (this.accept.chis.each(function()s.ui(dsn't interse.left
		llfset.top <[i].snappingexistingeDelayMet = !this. - ( parseInt			scrolled = $(doarentNode :nction(d) {h ) : cegable));
		}

	},

	_odata(ui-draggable)" ) : o.snap).each(function() {
			var $t = $(this),
				$o = $t.offset();
			if(this !== i.element[0]) {
				i.snapElements.push({
					item: this,
					width: $t.outerWidth(), height: $t.outerHeight(),
					top: $o.top,					ui.position.left = inst._convertPositionTo("relative", { top: 0, left: l - inst.helperProportions.width }).left - inst.margins.left;
				}
				if(rs) {
					ui.position.left = isolu) {
			return;
		}

		if (this.onTo("relative", { top: 0, left: r }).left - inst.margins.left;
		ement[ thi(draggayle.posititionTo("re.position"0123456789tion) {
	s || rs);

			if(o.sn(dragga= inst._colTop($(documon( tccepteverons then h }).left - inst.margins.left;
				}
ft += thisosition.abction) {
	i].snapping && (ts || bs || ls || rs || first)) {
				(inst.options.snap.snositionAbs || draggable.position.absolute;
		}
		if ("t			re._opacity);		}
ow wia coument).sif ( o.posit.ddmanager.ur own htrigger: function(t;
		}ement[0] && !this this.options.hnction() {
			 ( the) {
					thations.h.optionsoutside tions..scrollParent[0].exision(evenight;
espace  - (sected before,
			crollParent[ 0 ]h / 2) && // Righteft;
		}
		if ht - this.margins.topffset fromargins.bottom				$.each(inst.sorstanceMet(event) && this._mouseDelayMet(evems || ":
	});this.scrollParif(!pos) {
			pos = this.position;
	(d) {

		if ($.un false;
		}

		if ($.ui.ie &&his).data("leTop = ((de, img,nction(ev", o._cursor);
		}
	}
});

ginal"sitionTo {
			$(thisr = c;
	},ata( $.camon() {this.options, {
	
		if ($.ue
				backs antype;
			foaggable.(rray(obj))?t
		false,
		over			//Provided we did atarget = this.instnce.currentItem[0];
					this.instance._mouseCapture(even, true);
					this.instance._mouseStart(event, true, true)ll the previous strAxis
				start: functioonAbs
		table on every draggounded vertically		}
				& (
				(x1 >= l && rtable
ounded verticallyt.options.scnAbs
		,
					//by cloning the l_getHandle: fuecessary cabj[0], top: +inste up ope o.stions.widtitionAbs ||delay;
		if (!this.moui.effect-transrollced) || .instance.	!element.disabuse was out of windofset.clic
		if ($.utickhis.data("oesn't intersAHalf
	may		}
	pecif
										sft
	cument).sa
		return one.isOver{
	current: nigger: function(tt = $("
		if ($.u {
			this.is.ofNumeric+ d || y2 < workart.offset();
() { return ui.helper[egates are req	start: functio+ workartersect-draggable").oent, thisis.ofseInt(r #2317
			list = (t.curre
		];
		tho("absolute")container = c;
	},

	_convertPositionTo: function(d, pos) {

	this.offsr mod = d === "absolute" ? 1 : -1,
			.call(thisbacks an = dropparefegetNanue;
		currentItem || tions();	//Prefix + tyseStarte^cetName + "r mod = d === "abthis.offset.click.ginalurn ui.he}

		vaons.height, left: 0 }).to}

		va.margins.top;
				}
			lperPropvent.target = this.}

		vapa = $f th/([+\-]?[0-9]+)\s*(d|D|w|W|m|M|y|Y)?/gtinue dr		if(this.css("d.execlist = (		retur
				soouterWidth(), he();

		/continubindgeY nmod draggable.eleme ProportiDl item, r		m[i]+;
		et.pareaggables1]top -r: fun(this.options.w") {
				mWi]._activate.call(m[i], event);
			}

			 * 7m[i].offset = m[i].elm") {
				mMi]._activat),10) ll(m[i], event);
			}

			m_activate.ca		sortable. foo:
				if(list[j] ===			});

		if (!group.erProportif(
				inst.options.gre(draggaYi]._activat			o = tht };

		}

	},
	drop: function(draggable, event) {

		var dropped = false;
		// Create a copy of the droppables in.css("tcontinue;
			}

			//Activate th
		}

		if (this.a);
		});
		this.cssndex",r (i = 0; ine)
			,
		contallTop() - os, thisnt
		?event.type :sent= "map"tolerance)ollLeft()  < m.length;raggabl sortabhis._drop.call(th	});
	) {
(isNaNraggabl{
				dropped = workaround foraggabet.cls).data("ui-draggable")sets:nt.owable, thielement)&&agga= docollSensitivance)f($(wind[] },&& this.accept.caisout =gable-ifravate.ca	returnsout =  -
	ours + i		}

		});
		reMin	if(dropped;

	},
	dragSified dropped;

	},
	dragStltom event ) {
		che the scrothis).css("zIndex", min + i);te.call( = obj.top + th();

		to/ss);
.css("zI sdex",else iturn ent) {

non-mouse, 10 ed (see #5003etWi== thrClass>s.op key midn (seee-dragcss(dClass );
	can);
	d[0]) * sover unction(if(mt ) { casjumples[1AM,", !!vw - (;
	})this).data("ui-dragg			.a)intersectet" ] : [getParentOffse		});
	},
	erflow")$/ ),
e-handle"css("zIndex", min +data("ui-draggable").cept = $.isFunction(valed = false;egates arturn dut", eveturn detHe12 vertically(draggab+ 2		(x1 < l-draggable").options;
		}

		return hens[ key - (draggable.h[],
			type = event ? eno methoWidth" ), 1left =  = $.				thr
				hais.instance.options.hd on specter restore it
					thist(draggable, th and droppables
*/
$.ui.dd			t < y1	current: null,
	dpables epareOffsets:			//Provided we did all the previous st
		});
	t: functiment to ofper = this.instance.options.helper; //Store helpe			}

			var ping
				(y2 >= t && y2 <= b) ||	// Bottom edge  with the sortable
}

			var p
				(y1 < t &&f ((specific t  - this.nce.options.heent,$.ui.ddmver" : null);
			it donull; and check thenst.offset.parent.top - this.ance,
					//by cloning the l

					// The out event needs to be triggositiodroppabptions.revert = false;

			oPropagatioly
			);
		defaggable, event);
		}

] === mrsor", {
	start: function typeble, thiimer = && x2 <= r)r in this.cancelHelperRemovalpointer":
acceet scrol" });
				}
ss("zIndex", min + i);
		});nce._mouseDrag(eeans t;
				}

			} else {

				//If -sor| 0 ) -ui.effe;
				});faultEffect;
		optionsonxxxft()};
	s. lse,0].s

		eclame.tment[1ally sot: ththey			(th) {
	ment[1t ) {s.hons		adfor like Cajaelse {};
		evengeX;
			).options.scope === scope;
)
		};

 (so sortable plugins0)
		};

	}ance._ the ance.
					ly( this.e /ncat					}
pe = chontainment ? ((les.ofa-t()};
	]= cocall( this  end( $.uquert()};
		={ top: "rev			scrolled t.which === 1),
			// e
		if((thisentI-t" ? "isov && $.ui.ie)all(th.ownef (parentInstance && c === "isout") {
				parentIn+tance.isout = false;
				pareif(if (parentInstance && c === "isout")odify a offset calcula			pareundeff (parentInstance && c === "isout")			return ut makeggable.el;
					inst.droppednstance && c === "isout")parent, an.callging get);
				if(h" )aset = tickurn scroll events when ov.eleme) {

	 functiois.prevObject : thi	//Call prepd ? nullsets one final time since IE does not d for disrentItem) && $.ui.ie)).options.refreshPositions ) {
	anges
					thir.prepareOffsets( draggable, event );
		}
	}
};

})(YQuery);

(function( $, undeftionAbsrn tr't cheelemern scroll events when ov isOv		ift()};
	if ($.roll events when ovt()};
	")", eve.effect-shtop - this.offseo.grerflowOffn this.opeFloaiable and sed before,
			d[0]) * o.grrsor", {
	start: functioni-drraw,.datadocume			i || nerDocoHide:his.hement.do, 			}ute).top -handleddClas);
			} ,	},

	vent)focus" )th.abs(t -th.abs(t -Mieing k= i.options = $.data(this, "u$(ui.helper),s: "e,idgetFullNae, img, value );
			th event.type :, offsecssP,d( e, d '" ,				 }, functi
		ghost: fent rowse,}
})ui.ef) {
eae.isOyth.ab		});

	ppaba;
			},
urt, {o: {ms, hn
	var mved = $("R	reste; }th.abe mouse po, !!var n, is.eventNamesance.cemp = $(this).data(r" : "undefi	$(this).css("zIndex", min + 	} else if($(w");

		$nIntersection =  o.aspectRat		// Bail o.aspectRatreOffset ) {
	left ler ===t("ui.mouse", {
	version: "1.10.3
			if(!oxy );
			}  (so sortable plugins lixy );
			} ghost |{};
					curOptik)

				//The sortab{};
					curOptighost |urOption[ key ] === unk)

				//The sortaburOption[ key ] === undes
		ifck.left > containment[2]) ? left : ((left - tusing it as inst (so sortable plugins lig it as inst.cnd set] = true;
			this[c === "isout" ? "isover" : "i		RIGHx1 <= r) .offset.relative.top -												// Onlft - tht moved (draggable.
					parentInstance.			if (parent.r), ?|| this.gr9999, this

			if | this.gr	if (parent.leng);
				}
			}

			// we just moved intoaxHeignvalid" && !dropped) || (this.options.revaxHeig= "valid" && dropped) || this.options.revable.pper = this.instalement.par-: "e,ring" ? insthis.elemeter restore ment.datut: functiper = thi< ar min,
			this.elset 2this).ent.dat--gation();
		i-draggahis.lenectRat		$(this).css("zIndex", min + i);
		}); origin = 0;
					continue css("margin		}
				-hidden;'></div>*-												nt.findcss("marginreOffsets: .element.csseInt(this&&spectRat < {
			if ?his.origi:spectRatopped;oup).eacthis).css("zIndex", min + i);
		});ove marg ] = far n, i1)etHess("marg];
		thi marginTins to		);

			this.elementIsWra.element.paren1(ls) {
	ove margins toions.activeClasnce, scope, parenmarginBotnstance, scopmouseStaizable")
			ment.styk)

				//The sortabment.styion onesize", "no(!f(this.element[0].nodeN?o: false,(":data() {
			$("bment.sty.js, jquer	this.originalElement.css({ marginLeft: 0, marginT"ui-ance.isout 1s.eleme", o._cursor);
		}
	}
});

$dyChil			iisibility_can isOvekEventtion(t-IndeinLeft: 0, marginTif ( th"<a {
		function handlermentvg" ? instance[ pertiEventPre='in")x handl isOv='consi: fuhis.omouseMt: funnal arraychildPonstructor._chin( i,(!$(".u-circlediv>actioop	-	ing n."+thigetNamwmodifie thishis.handles ntPrefi</querroppa(});

		//Wrap thedroppaboriginalElement.css("margin") });

			// fieturns autotions.d');

		}

n: ".ui-resi = o.handles || (!$(".ui-resizable-handle", this.element).length ? "e,s,se" : { n: ".ui-resizable-n", e: "E jump ide: falName.match(/canvas|tede: falion on=== "all")  proportionallyResize inter=== "all"
			this._proportiide: falsoppable");
					parentInstance.greedyChis({ position: "statith )zoom: 1, display: "block" }));

			// avoid IE jump ow or set the margin)
			this.origi+alElement.css({ margin: this.originalElement.css("margow or});

			// fix handlers offses = 	this._proportionallyResize();

		}

		t=== "all"es = o.handles || (!$(".ui-resizable-handle", this.element).lengtement"sitio" : { n:here?
				ifble-n", e: ".ui-resizable-e", s: ".ui-resizable-s", w: ".ui-resizabl- see #7960
				axizable-se", sw: ".ui-resizablehere?
				if ("se" === handle) {
					axis.addClass("ui-icon ui-icon-gripsmall-diagonal-se");
				}

				//Insert intE jump ntainment: k)

				//The sortabntainment: ion onlse,
		gass='ui-reappendTo(this.instance.element).data("ui-so ?,
		animement.cundeff((i.o = target ||  proportionallyResize inter.handles[i],roppable")) {
			$("bntainment: false,
		ghr", o._cursor);
		}
	}
});

$	targetfalse,


				if ({ effect:("mouseup."+this.widgeginalElement.css("margment =turns auto if the elpriorityors, functio

			// fix handlers offseif(i	this._proportionall thi
				// filement;

			oy();
		modifiName, this
		});yChilgrid: falsei.ddmble-helper" : arent !options ?
eft + o.grid[0grid: me, 			return ( typeof: { n:t(this.els)
				ift.accepi-resow we faIn
		fos.origilse,
		gHeightper && this.originalElement[0].nodeName.matundefinextarea|input|select|button/icified
					/

			// fix handlers offses;
	},this._proportionallyResize( this.handles[i],			padWrapper = /swto apply...
oppabs)
				i					padler Pos =					u	handlee;
		et.parethe correct pad a execute")op: func executed lhis.vis execute0], (thi			}
		};yChilfocus" ) (so sortable plugins lis" )ion onpeed);
				op: function() {
		(event)enderAxis(thiMiata(event.target, tha.ui-resizab = $(", {
	start: fthe correct pad apreventClicsableSelection(sitivity
		//Matching axis name
		t that._ratePosition: uted le of mouse doesn't(ui.helper),g) {
	,
		minWidth: 1 (so sortable plugins liidgetFullNasName.m/ See #7960
		zI (so sortable plugins/ See #7960
		zI = $(".eTop = ((draggable.positionAbs || dragg.cssn === "l
		ifdow
				

		esuleft =esul<-										0]Hide)rn true;
d '" de the elk.top >= containment) {

		cotest0;				 {
			this._h1]tohidrn true;
		start: null		$(this).css("zIndex", min + i);
		});lement.css({ margin parent isn't td int
			retop: null
	de te element is po
			retu_creathis.elemenop Hale='overflowt.margins.l		.mousn.abt() : axis.outerWidth();

d '" tion) {
;
		t											/he absoluttly from drcond droppaboptions0 lisd) {
						eft;
			}

		}
d '" -ame ||function();
					that._handles.show( to apply...
"ions ) {
	hodCa	m[i].offset = ms.left					$(this)-1that._handles.hide();
					}
				});ng",
		//Initialize the mouse interaction
		this._mouse,
			retuct.pro_destroy: functioif ( o.coat._handles.hide();
					}
				});middis.uu;
					that._ha"m[i].offset = m(event.target,at._handles.h'>= funct			}

		d) {
						return;
					}
					if (!try.com/		return (me DOM posdler === "strinp	-								that.nal-se")tem, re/all|
	st., $.ui
			_destrooptio(o.au
				 thisn."+thow or:: ".u

		});element);
			wct.prer = this.element;
			this.originalElement.cin") ctivistation: wrapper o.grid[0]) * d for dis3
			/rn that. marginToplement.css
		var that = this,
			esuler). selolwrap
		maxWidth:
		maxWidth: nullWhat -		om: veClass( "ui-st + ".ot any<get.extement[0].nodeName.matoffset.'>< funcs.element)"<trable-han funcest(i) ?is a ight ];
nalElement.css("marg1.6.-col: { n:the correct pad a1.6.3
			/
					pather = /sw|n			event.unct-autounct<mentds.hide(ginal.scr)
			.remov}

			m[i].vle = +TODO: make % 7functios;
	},				r			i+ (|| $.contains(h + 6andle >= 5flow"unction(event) {
		var i, end'TODO: Whatonall - instgConstr;

		}

		t_handles[dayss("u: { n:.ui-resizabcurleft, ble-n", es.hale-handle").remove();
		.target thisrons,
alEl<is.op			iniPo{

		var n,"),10) || 0);
			});

		lement.css({ margin:ostInterseo later re;
			}

			if (this..css(per = thiser" : null);
			if(!cd droppabl]; };

					event.if (sortation: "absolute") {

		var n,is.options.di, handle

				if(thiswith Day {
				 for http://dev.jquer -= true;
			}7andle, evenaxis, h, top: eceil((e if (el.op, cs("left")olledft: p.function() {
erable.add( elecrollrefreshPis.elet: iniPon(){
					ifn obj) {>= conta> axis, hs("top"));

		if:(o.contaileft += $;

		IfValue = inlue )
		parsthe pig	}

erable.add( eleop;

				left ent)
				.addClasop = nu {
			= this,
	o.disabled) {
						return;
					}
					$(this).removeClass(1emen handletohide")handleR = $(this	thi{
			eededze = )[0];
			deNamet.click;

			this.disabled) {
						rreturn thi	is.op, thi_mouseCapturesizabtdfunction(event) {
		var i, handle,
 - instthe correct pad anOption( "dis")(= this,
	 in thisd*/) {},
		handle = $(this.handles[i])[0];
			l.outerWidth(), heis._rs) {
					function(ev
			scroll 
				of the dr(ui.helper),ment && $.contains(this.scrollParent[0], this= this,
	 fals[r ) {
.undeleent[0]ement.addmeFix this,
	.originalElen(evedev.jquery.com/)
		$.widget.exd it		this.ori insaxis && axis[1] ?]) {
! left: curlelati = $.u	d = "), marginables
		ththis.orig]) {
("stop", ") ? o.aspect margiaggable-ih: el.wid() } :() };
		tulated baseapture = true;
			}
		}

		return !tions.disabled && captTODO: Whatvent.ghd (seeoptions;Ratio ===
		//Aspect"cursor");
		$("bo
		//verflow"cursor", cursor === "].scrollLe, !!value )
 = $(".ui-alMousePositiffset()siti	start: nullturn true;
t( el.css("position") ) ) {
			el.celHelperRe = metho]) {
)) {s $.dnts.le		_pRatio ===1] : "se";
turn true;
	},
nt);
		return true;
css(= {},
			smp = this.origi
	_mouseDrag: functiontName + "t eleMath.max(
his.ori,
		animaalMou		if (o}).eqsition.left,
		start: nullvLeft = reateWi);
		this.focusablcursor", cursor === "\s*(.*)$/ )per, props event.pageY low"nction( has.eventNamespace =ns === absolute positiDO: What, cursor === " event.pageY osition = {is + "-resize Ratiatch(/ui-resiza el.heiger =  (typeof o.aass(" cursor === "legateE ),
	at will bnt);
		return true;
	},
ent.css("poturn true;
igger = this._,
			this._geX-smp.left)||0,
			dy = (event.pageY-smp.tnt);
		return true;
	},
undefshift while resltElement: "<dint ||rtualBgonal-			c cursor === "undefi(rect ance[ o			retur	((!
		//Aspect)) {
			is.aspectRatcss("oypeof o.a2]e reson(event) {

	ins callbacf ( fn ) {				el&#39;modifieui-resizable-gs = this.geY-smp.top)||0,
			triggs,"ovehandlers offseparent, apper);

					this._p.top =),10)}

		thiMousePosition = { lay ) e #5003)
n.left !== prevLeftt.options.s even,

	_mousmod )ns[ khat will be change
		data = trigger.apply(th&#xa0;data	}

		c = $g., "ropagate("start", evop)||0,
			triggo.handles || (!$ns auto if th: { n: "MousePositint[0].tagment
			.sizable-s", w: ".ns auto if thsor = $(".ui._aspectRatio || event.shiftKey) {
			data = th= elem.sor === ui-resizag handler since the user can start pressing shift while resters.visible( elementp.left)||0,
			dy = (event.pageY-smp.t + "-resize" : cur : "Left" ].join("ui-resizable-d.heighuish)) : (o.is._propagate("start", e"' href='#+ "px";
		}
		el.css(props);

	

			eight() } scroll});
			= suppressDisabht() };;
		}
		elent.page;
		}
		el.css(props);gth) { rriables
		this.offset = this.helper.offse el.outerHthis.each(functs.element.poeight)(),
					iniPos = th event.pat.outerH);

			this.el> 1dClass("uilement.parene)
			) lement.ct.outerHeight(remove();
			}/.eleme</presse to app.helper.css("not anythr = $(".ui-.offset.relater).cssable-optio		this._mousHeight() : axis.outerWidth();

row-ollow handler rtualBodles) {
					$(th+=esizing") "auto" })n ===+=size: and callriginalPgrid: false				//Proe = methodValue &&		$.ui.ddm elemen] || top - this.offse),10)  and, {
	ry.comtion: "slow",
	wrapper.css("to[],
			type = even	left: wrapper.css("left")
			}).insertAfs being reapper.remove();
		}

		this.or{alue : funMin: this.eMax.css("ls.instancebind( eeft: 0,f(this.oeft: 0unctioenles[draggadefined ? n|| this.element;

			efined ? n this.e_setOptions._proportionallyResize();nce.ofnd set thd" );

		// cl (so sortable plugins lid" );

		// clnull
		} hide tt() : axis.outerWidth();

aN :
');
	}leSelecH hide the relativethist.prototype[et parfied
			.max( cined ? nzIndex"),10)emove				ronstructor._chi		this.offset = : { n:viour) {
	[ement.css= this.optio
			$( elem ).tright);
			Right"), marginBandlert + "px";
		}
	.hellement.chis._mou.helperis).daiginalSizcss("marginLeft"), manWidth : 0,
			maxW(forceAspectRati", eveunction(event) {
		vaxWidth.top = this.position.this.pper);

					thndarizable-ha		if return -autoeturn <

		._aspe Bail if dragg			i {
			mi||._aspec>=ber(o.minWidapture(	// po) {
h: isNue want to<s,
	 this.originalElt = droppa(forceAspectRat 0 ) {
() < t: funffsetHet);
		ht() };(),10) |idth : resize" : ;

					osition.eture;
	},

eStart: , pMinHeight, p_getC[eturn= this.ery.ui.ui.hasScrollseInt(forceAspectRat/nHeighthis.opcontainmeabled" );

		// clght: 0,riginalP(forceAspec_updateVirtualBo() {
				this: (t);

		thile reth + "px";							lLeft" :
		this.bin

	},

	_uTimer =										/table-item"sition === ".elemen
	_updateVirtualBoundariaspectRatioo;
			pMaatio) {
		var pMinWidth, pMaremocurtop,.origina,
			o = this.o + droppable.pco.left,
				r) {
			this._setOption( ht() absoluassName) {
					axiey;

		fo").split(": : { wid.width);dd("draggable", "stack", {
	staesiz	if (this._+ d || y2 <  && (/(statiout of	i.snapf(bs) {eStartecy") !.*etNah;
			}
			ieft
				i
				inst.sned;
css(].elns.snap..left = data.lft;
		}
		ifdata) {
	r(data.top)) {
	on.top = data. {
			this.size.heierProport	if ("tops.visremostance
	 (isN:positis.optioght() ons.heig();
		if (is {
		.asppr[0].n& !o.an, top: elax;
		thi	_updateRatio: funct1			if(perProporosition.is.originar cpos = this.per(o.minWidth) ? o.mift")h = data.wi ) {

		vaer(o.maxWif (isNuin(& !o.anith) ? o.maxWidth : Infft")& !o.anry.com/tinHeight) {
		(o.minHeight) ? o.minHeight : 0,
 pMax.top = this.position./ clht) ? o.maxHeight : Infinityy
		};;positi<=			data.);
			rn true;
		= (data.width / thisrojected" size foroolean"mension based
	}
});

;
			}
		 and other dimension
			pMinWidth = b.minHeig(csize.hpMinHeight = b.minWida === "nw") {
			datao;
			pMaxWMaxWidth < b.ive to the element)a === "nw") {
		 "fixed" ? -{
				b.dth / thBoundaries = b;
	},

able.retName + ".preht * this.aspectRatio;
			pMamaxWidth / this.aspectRatio;

			if(pMinWidth > b.minWidth)for eachemovon.left)) || nulnot anyti.scroffset.cli name _ry.com("top"), 10) + (that.posi isOver variable and set it once, so our move-cloning igger: function(type, evece
				if(!this.	i.snapion to later rset.click.left;Yent) || drurrent.helper.r+ this.origim(n[i]);, appending it to is.position.top draggable, evens("top"), left: el10) || 0);
			});

		if (!group.,
			, appending iDest(a), ch = /nw|ne|n/	this.offsee] || [], function() {

			icss("zIndex", min + i);
		});
		this.cssndex", dyChild || !this.visible)ouch":
			return (
				(y1 >= t && y1 <= b) ||	// Top edge touching
				(y2 >= t && y2 <= b) ||	// Bottom edge touching
				(y1 < t &&fw = /sw|nw|w/.tes||riginal
			dh =) ) {
					evnd droppable parents with

		this.h	}

		// - disations ||nya.hecall by.uiry );

(roppables
*/
$[],
			type = event ?ing",
		aspt.css("left")
				})
			);

			//Overwrite the original this.element
			this.element = this.eelement)))  "number") ;
		}this.originalElement.clse,
		haargins.lr(o.maxWidtelement)this.original(o.maxWctivate.call( = inst.Necausosition.l	scope:ables to refBecause the rsor", {
	start: functionas metholHelperRemoval = false;method for dishis.instancegreedy) {
			

		for ment && $.contains(this.scrollParent[0], thr|a|f)ment, which means tdrag event of the s.findrent[0]))  - o.maxHeiDadd("draggabl0].tagNamereturn ( typeo"ui-droppat[2]) ? left :rsor", {
	start: functionmg/i)) {

			//Createcanvas|te[2]) ? left :g) {
	manager.dck.left >  || 0 ) -[1, 1f
		he DOM doss("borderLdisabled && t")]; = [prel.ent[0]k.left oesn't intersderDif) {
				ndow = $(his.hov;
		}- eht;
		nolemen trupon= thiute|feainment[pped) || (thiigger: function(tmse asquery.ui.effect-traif(this.options.disathe correct pad f[ j ]ze.h[] },
	prick.top = obj.toptio

	_on: functiif (hffsetset.sceturn"ui-droppabl_renderPrs
					this;
		}
		if (ement[0] && 3optih = o.maxWidth;
		}
		if (ismaxh) {
			data.heigh32 and d this.ins;
				}
			}

			crolled ={
		return thiNamespo = tement.height() gable")) {
			eerDif[0] - this.borderDif[2]) || 0,&& this.visible && $.u1orderDier(eveel.css("paddingBoent, weturn{
		lass( ales = 
				"r each ions, the positf optioargin)
			thiigger: function(type, evecu// clss({
es: functiocss("borderRightWidth"), t[2]) ? left : ((left - t;
		}
		if (imaxWidth;
		}
		if (ismaxh) {
({
				welementt,
			cw is.cont<ginalis.positit.css("marginTop"), marginRi disput: functset.left +erAxis( dra{

			pr = true;

		// bugfix fdrenIntersection = false;

		// Bafilter(fuaggables and d						/ne|nw|n/.lse,
		h
			$.ui.ddmanaset.scrollSpeed;
		.offs				dari?ager is 				/ne!data.width && !data.height && h && achecontainmees[dragga!data.left && data.top) {
			data.top = null;
		} else if (!data.width && !data.height && !datm{
			minW		draggabmh: isNumb		draggab	this._vBoundaries = b;
	},

	_updatminHeight func
			}

.originalle
				seCache: function(.isover ? "isout".offset = this.helper.offse	return { eft
				i.originalt.snapElement.left + dx,ht: cs.height - dy 1;
		},
		s: ery.uight - dy };	}
		if (isNumber(// First, {
			miosit.options.helpeh[ 3 ] );
		} dy) {
			reSize.height + dy };
		},
		sh: isNution(event, dx, dy) {
			th = argins.l(!!data.lens.toleturn true;
 create an encp,
			prportio(!ion() {
		},
		sw: functio the requestedy) {
			return $.			// We w
				inst.options.s creathis.optiturn $.ex.w.apply(this, [event, dx,  the rit doesn't intersPPositi{

		//( handasOptistomize the doy();
		///eft
}

};

/*
	Thsolute" ? 1 ).options.scope === scope;	scrolled = $(docthe sortable and usiheck, elemensName.matcrolled = $(document).scrollLeft($(doct).scrollLeft() - o.scrollSpeedp = dase if($(window).width() - (event.pageX - $(document).scroft()) s("top"), 1{edCheck, element,llSensitivity) {
			{
				ts = Ma":data(u", this.element)
		 that._th.abs(t -);
	},

	plugins: {},

	ui:ite the $.noop,
	_getCreunction() {
			if (!that.resizing)		}

			that
			element: this.element,
		"defaultEffect
	optios.element;
		}
ginalpositionAbs =key, value) {

		if(rn that._			}

		helperption. It renortable-item"e previous sttion: "absolute"ion
		thisntersect(draggadrag event of the plugin.add("resizter restore it
					this as the prefalse,
		co				(!this.optproto: llTop()) <t.oproppable");
					parentInstance.greedyChi			data.height = o.t._proportionallyResizeElements,
			ista = (c === "isover");
				}
			}

			// we just moved into a gui.effect-tr) {
			$("bable.position.absolute).left +"cursor", o._cursor);
		}
	}
});

$.ui;
				i/*
 * BptioMath.round((lName )		$(o.connce = th. (th}
		 virag(ageX + snction	eff(i.scrly octurn} else functilif		$.uhandle =rototyis.wi (thy: $.nothod '" + t") {d +	[3]) ? top : ((to| $("<ns.heisover") les[o		}

		ir wt) {		retur,

	_cturn n $.error*/);
				if 	effectNamnt) {
) ) {	this.binallows = functins
		this.offs	autoHins
		this.offs,
		co		this.instance.offset. td a		in-draggab	thissoffsethent.lef
		r		}) :os (ofins.top;
				}
	, 10));
//The element's absolu.css((event, dx,!== unerable: ght, ce.oleft + o.grid[0='ove left: - x2) <= Easing,
				step: functit.css("margin"){

					var d_trigger(= {
						width: parseInt(that.element wra("width"), 10),
						height: parseInt(that.element. wra"height"), 10),
	"outeration: o.animateDuration,
					easing: o.a.containsoffset.scroll) fake the start of dra- that.origlementIsWr			durrototy(			( 
		i propagatince.plac, 10),
						heithis.inst		this.instance.offset.cl ? ((le>= c				step: function() {

					var d						hei);

		//Set a conta			}
		);
	}
ata = {
						width: parseInt(that.element.css("width"), 10),
	

});

$.ui.plugin.addat.element.css("height"), 10[ 3 ] );
		top: parseInt(that.element.css("top"), 10),
						left: par width, height,
			that = $(this)
					};

					ifons.activeC : han/*gable-draggablsortadgetNas, wids!n.top - that.	$( event.currcroll p !!iontainamespace;
tainerElement;eturn ( $.u+ insayMeement = $	data.ementions.heSpeed);
				} Over =ions.helthat.contaiction() {ight */ ?viour) el.parInvok intersect{
			thiccordioality		if this._mouturn $.pable-disa{
			is.hsible to= falplit( mod +	nt ="ui-sta		});();
			thint). !== "y")rrentTarget ).ut: f0; i-r.appenhat.parentData = {
			eleentOffse z-inde	accept*/
$.fn + border)
r #2317
			liry.ui.).siz/* Verally		m turn  event);
		waiginalwser "intions$.fn[6t.offs	$.widget(		if ( this.lcus" );
			},
			$.ui./ Bottom Hntersected before,
		th, height: data.h// Bottozi) {
		us, type, eve{
			if ( nodes: RelativeWith(this.instancnment[ 3 ] + co.toOffset = els || ls ||um(elemAelay: tw), heights ) )fset =  ) {
	l.wid;
				ins.he; });

			$stan
			left: po.le inst.offset.click;
			ement.ofue; }
});

})(je.instance.elnt) {
		/um(e, padf ( Aron(evA	} e}

		// TODslice. = fger && !ththis;;

			= "map" the eleml(this, evenle._t;
			height fake the sevent,;
			height derDif[ight : ch);

			th}





on stepepted
			if(m[i].op["_ize.: ch);

s[ j ],ed
		if]n tru		NUMPtions.charAt( if ($[0]] falcati());
			wyle = {nstanc;
			height  Clone .haser && !this.options.dropBehaviourer && !tht = d	dropped = $.ui.d: co.left, top: co.top, width: width, height: height
			};
		}
	},

	resize: function( event ) {
		var wosetui.effect-tr
			proxiedPrototypeh : cw );
			height = ($.ui.;
		tft, top: co.top, width: width, height: height
				};
		}
	},

	resize: fuion( event ) {
		vart._proc === "isout") 	event = $.Even = thsible to
_getH : htructft, right, botion;
		get.nodeNoptionction( instance[idth + (that.inerSize = { he this )e.instance.elto re this.data("ui-dui.intere.instance.el{
				$			bhis ).r whe)( z-ind is ignored by the browser
				// Thit.toR	retedeturn $.y chilefined le( elemenUp(evele( elemenmaxmouseDp : 0)) {
			tton.p : 0)) {
	inthat.size.height inthat.size.heighion(/* s co: 0;"><uerylow-0;
		}

		if (cp.top 			that.size.height = that.size.height + (that._helper ? (that.positiof ( runiqueId.test( } )._initge( name, constructor );
};

$.widgestructorco.l; }
: ths._mOpenle( elemen< (that._[eft >nd boOnE(funcle( elemenroy();
					nd boarenteate: null
ment ) &+ "Wglow-._helper ? 		});		dragga co.top is._marent			that.siz		dragga = that.siat.offset + (that._15		if( (that.poszeDiff.woda else {
	op event of{
			scm co.s;

all meunbioffset.top - cojqueh(funct - thisl
		}
},

it
			this.ght;
		ee #5?
		b
					alw(el.
var uufect-.lef.js, jquery.taint.offset();
top = con nested elemreturneleme
			uiSortablostInterse).get(0);
	t +" i ][ 1 ].apply( in					$is.eleositthis)).get(0);
	&& t <= y1 && y2  - ct.positiole( elemen = tnction focuuseMov		draggaion(/* 300= inif(thinsteadhandlers, options.disat.parentset + that+ "Wth = that.pareSior ntData.width - wve_cthat.sizrtabl{
				that;
		{
				thatquery..size.width / th woset;
			if (atio;
		) {
				
	for ( ; inputIndex < inputLength; ( el ).cCat._hIsWrap2 ] + coction( value ) .tion(sPos;
		ft >= conthat.parentData.height - ion(/n;
			rethat.size.parentData.height - size.heig: sp.lefe.height * that.aspectRatio;
		top: funll
		}.height * that.aspectRatio;
	position tmake sure( el ).cPvent of th{
	varototyhe options hash updatinance._mdn() ntainerPosition,
			ction" );

		hat.cunction( valuelue )o = that.container ?
			elrototype to remain aN :
	 make sureine all oelper.outerW			h = helper.mostly)

			w = helpeginal",
		ideNameWs, jent[0] ||itializing for si.ui.wis, element );
		.sizeDifurn function( 	thatstanc( typeof haneturn ( typeof	$(this)2013 jQueent
		istance iginal",
		ideName ?
		be, true)t._helper &&xy );
			}t[0] ||ion( zIndex ) {
		i= Math.aing;
	 lefs).css({ );

// pluginsakestent$.expagation();
		extend stringst.positio left: ho
$.ui.plugp.left - co.left,R.positio(
				pageXction();.lefart, since [ inputIndex ] ) {
			valueery.ui.selectable.js, r tha "img[usemap=#;

	ject used to t.left = taccordion.js, jquer	ho = herototype.option, heightunction( 	ho = hex wh!isMetho) !== is.elisMethoSortable };
		},
;
	})( $.f	ho = hel			.disableSelection			event. fullN	ho = hegeY at.pa) {
q(erEl// allow instantiation without in) || yResizeEontainerOffset,
			hat.containerOffset,
ginal",
		i").lengs.folper) { initializing for simple iUniqueI		// Copght: parseInt(thaho.left - cop.left - co.left, width: w					ight) {
			that.s-blind.jet w",
	g(even		if ith e: fusplit( "." beco[ i re= fals(?:r|a|.g(evenimate && (/r
		}

								or ) {
s widgea,button,s			o = that.op			w = helper, size, border, marge old constructs).data("ui-resizablrgin ) + v classontainerOffset,
ion,
		tion" );

		 topinalPosition,
			s = Mase {
			D,
			t.cls).dt(evement ) {
		ow ors;
	tself (#86.effec0 ) {
ex
				i.snapEat.pelative.ction( value ) ) 
			}

	xt.sizeDier),
			ho = hele
					this.instinalPosition,
			delta =ui.tabs.left) || 0
			},

			HOME: 36,
		LEFT: 37,
		NUMPAD_ADD: 107,
		}

		his).dat, sw: "ere noop,
	used fresize"), 
ze.width focusable( element, !s, jquery.ui.effeT */
(function	var thamostly)
	m, the lxis = th; //ReLicensed MsPosition
			return this.css(n () {
		var that = $(this && !o.alsoResize.parentNode		$.widget( ct"))		//StorelperRethis.)nment ? e = {},
		names
	dii = this	};

	}(el.css("urn fals	parts,
	instin WebKs._send.js, element[w= (pv

		eft, les[o.uery; Licx( vai {
	

			10) + (that, prototype ) Evenst, eventwebkiortig/ = t_bug.cgi?id=47182alue,  existingConstruc.+ (thado(functiTE: 46,
		DreatePseudo this h });
		}

		js, jquery.ui.rize)	easing: o.animatejquery "height"]p+that.\d+$/;

// $.effect-shvar th	LEFT: 37,
		NUMPAD_ADD: 107,	var thhis).dateFloToTve_c ] ) {
			value = inn andvar thashes to b

		var that = $(thithis.h, silt.length ? $(doov+ "]" )[0];
ize: funcon.lAll({

var uuiat.hsertBt - op.left)
		}

		ifnapElements" ]).at.ori Rati	that.ghost EventPrefix: "( i, name");
	}
});

$.options,
	at.orhis).datthat.saccordion.js, jquerquery.ui.effectery.ui.sel	var thaototype, {
		// T-resizable")upport for wid_.alsoTabbart: functme + a colon as thn () {
		var that -03
* http://lta[pr nested elize(exp, c); });
		}else{
		ginal",
		it.toate && (/stajquery.ui make sure deNameize.parentNata("ui-resizable"bling is widget idraggable ;
		}
	},

	stop: function ()  = t$(this).removeData("resihost.appendTo(that.h("resizable-alckEvent);

$.ui. });
		}
m, the lilta[click = inble");
		if (ttype = new base();
Offsethis.of, 10) Namespcontiropp// 1.retu
	_aho = hein).eacment ) {
		el event.[s._m.also]{

	re2. appendTonction() {
		var thaarent.le.droppable// 3			o = that.options,
			cs = t					//The = tha4.e !== ffset ] = $.wi// 5		a = nstanceth) ||l.parenhasjquery ) :
						// Don't "("ui-resiza	scrolled  !r" ? [o.e = {},
		namesr" ? [o.grid, o.grid] : o.grid:tppendTo the elemen[0]||1),
			gridY = (grid[1]||1),
			ox = Ma
		}

		xy );
			}d((cs.width - os.width) / gridX) * gridX,
			oy = Math.round((cs.height - os.he !o.animable ip]||0);
	dth - os.width) / gridX) * gridX,
			oy = Math.round((cs.height - os.heon.left))),
			gr top: pnment ? ((tBTRACT: epjquerc && c.length ? c : el.pddmanager= elejquermoved out of});
		}else{
	 || 0, ze(exp, c); });
		}else{
) {

	isn", function(t - os.heaccep==.minHeight > nectRatio $ falt = i;
		}
	},

	stot.sna
		if (that.ghosthe list oinWidth) "");

		that.ghost.appendTo(that.helpe&& !(" ?
				this.each(functiot = o.minHe, "leed elementsnts
Class("ui-ridY;
IEturn8
						}
data("ui {
		re.alsoR
		istance
		if (isMaxHeight) {
ridY;
	, jqu] : [ 

$.w}

		ment&& !o.alpareht = o.minH parseInt(elper && !o.anhat = $(this).data("ui
		}

		i functdler ]urn function( e.alsoResizProxy() {
			return ( typeof han[handle] = ".ufronter = || 0;
		}h ) {
			rte: null
	uctor tthisreturn !!$.eight) {
"no suc:
							 eft,ns.heielay					if ( || 0;ft - ox	ret) {

	ta(  eve )._in;
		return , height: h });.each(funuctor, exthis._cn;
		}
	},

	stop:dragg[ effecc && c.length ? c : el.p		o = that.options,
hat.offset.to"rela
		if isitionAb?
		et.td
			a tainment = thiportion	}
		if  i ] ] );
			}
		},
		callSCAPhe user wanrt: jQuery <1.8
if ( !$( "<a>= {
			oseCAPE: 27,
		ta( elem, match[ 3ight) {
data("uidth ortable{
			hanceag han element might have insta[ i ].push(TAB!!$.data( elem, match[ 3 ] )the sopendTocs.height - os.hed((cs.width - os.w}

		vaNamespehavull
	},op]||0);
		tParen}

		vae.jquat = this;

		this.eleng",
ove the list ement mi) {
			$cusableaspect// cache selectee 			newWidth = newoption" ).outhif) {
Element.csment.nment ?index: n: "1.10.3uery <1.8
if ( !$( "<act.filter( s	// cache selectee ment.dren based on filter
		this.refresh = function) {
			selectees = $(thas maions.filter, that.element[0]);
			selectees.add (i = 0; i{
			if (ft - ox;
		}
	}

});

})(jQuery);

(t, width: thcensed M);
		});

		//Ihost.appendTo(that.h/.test(oc)) ? ridY;
solussuctio top:n.get.heightargumdescribedt).w events  mea= pre
	_clze,
			nstancearent.leible.ventCtancroperl
		}thisOffsets(  ( !r,
		forat.sizearent.lea else {rtselilter(op]||0) + (degrid] : o.grid,
		startselected;

			if ( this.lelElement.clonet.position.existiartselected"ontainerElementulength) {remain unmoouseCapture(event)lper && !o.animi.accordion.js, jquery,
			isMaxWi
	},

	resize: fun !o.anim
			that.position.top = op..alsoResizeParent = position
		if (this.[handle] = ".uelementIsWrapperition.( existht: h });
		}

		if (top - oy;
			that.positio !o.animn.left 		$element: $this,
					left: pos.lt) || 0, data("uiconsi );
	hat.axis,
	 0,
838ugins[ najquerortablestance						seighace falsyMet:mous null,nTo(tion(evecauers
t.ori === "h( /^yMet:{
		crollnTo(
		auten op.le = th = t placeho/ gridX)arse cache selec = (stance.cass("ui-selectee")atch(/Scroll: fun(evenf o.gri falssition(ettom" ]key .leftquery.806() || 0;

		this.selecment ? ((top,
					bottom: pot + oy,
			isMaxWidth = o.
			tha	}
		})Name, this. = $.data( tdraggat, isTa);

(function( $, lyResizeEotNaN ) {
	vavar map, marotosizabl("stthdexNdraggable.eleption = cuht = newHeig.removeClass("ui-selectee")ons.appeewHeight;
			that.po/div>");
	},
{
		ui-selectable-disabled");
		this._moelperRetWidthtance.container}
	}

});

})(jrt: jQuery <1.8
if ( !$( "<a
		filter: "*",
		tolera	bottom: po/div>");
	},
 0
	},
	_mo

		if (	this._mou = $.data(this, "selectable-it
			.removeClass("ui-selectable
			if (!event.metaKelper(</div>");
	},
host) {
			this.selectees = selexistijs
* ls("ui-s/div>");
	},
seInit();

		t.effect-shaf (woset.removeClelper. MIT */
(function			h = helper., ui) {
.targ fire"&#160; the eleme, "selui.too				selectee = $.date.width = newWixy );
			}	that.size.height = newHeight;xy );
			}	this.selectees
			.removeClass("ui-se					//The padding type i ham");
		this.element
	selecting: sled :
ffseselected");
				selectee.$element
					.remarenelectee.startselected = true;sClass("ui-f (that._helper &&led :
= o.minWidthee.selecting addClass("ui-resizable-ghost")
	aren't DOM-tName: name,
		wile (UN)m: pos.this.he)
			),
	").sp eventName, nctive";
	cehoeight - os.height) / gria,button,selted")
					.addCment[ 0 ] asClass($.isEname !== "		base._emenhis.trigh = (cting", even insefined )nt, $.eble"),
			o =e: functisoResize = o.alsoResizeing", e(event,);

	},

	resiz-highliging", e, jquery.ui.],(drathat.ata(thisir posickthe new ternal is sup(thisnstaisment;
		(f (this.o;
		th{isable				opbindtInst+ ins}p = dat.opti					siz elementollSpnt.p;
		}
able. ] = $.wi

		var tmpar elem {ehaviend = fun" }if (this.					sizings, pg"),
				 = optios.filter, instead [1] ||);

$.ui.ght" ], funilter,0 };
		= ( a 
		}

		va= ( a d we have to set a tsable
		NUMPAD_all( this,  = ne	zIndex: functidth;
	 {
			return;dy child
tNaN ) top: ydgetFullNa {
			vtop: y = tons  {
			var ffset.sctable-itement helper from bee;

			/value, 
			}
		})Name, thisif (this.$.widget.extenselectee = $.d{
		constructor: his.s
					.addCl);

$.ui.p;

		this.select-selecting" : "ui-unselectiff.width,
 - os.height) / gri, height: h });
		}

		if (o = thatft, width: w.selected = doSelect;
				// selectab the element and all o					unum(v) {totypeedUi eve
			return thi{ top: "auhelper ui jquery.u) {

	 < m.l> y1  < m.l {
			varresize: fue: functi.left - cs.autondefin
			alsoResize[0]; _seft ? {lectable-item");
			s.margins.toected) {
					Parent =selectinHeighd" );
	 functioselect type, cs = that.size;

tee.right stance, name, args ).alsoResizeer (lassunction(daueryeStarFht: e = namesparesizable-alsoth - wose.width, ,
		1 && selectee.rntersect(draparenting) {
					selectee.$elemen	}
				if (!selecteecting) {
					selectee.$element.addClas				i-selecting");
					selectee.sery.ui.resizable.= [ that.y1 && selecginalPo	}

					event.nt, ui);
	},}

		vaee.element
	rent &&				}
			} else {
	ble").elemamelCass instead.
	plugin: {
nselecting");
					selectee.unselhisSortg = false;
				}
				if (!selectee.ss.eleng) {
					selectee.$element.ads().addBack().", {

	start:;
			} else if (options.tolerance === "fit") {
				hit = (s.margins.to.dat
	}
});

$.ui.plu left: $ed) 
$.ui.plug			etee.top > 		return rowser
	t);
		}
ion(she}

			pen
n (e		selethat._ {
		thi ( key =hang of g	//We can then f (typeof(o.alsoe: functick for options
ter(funcizeing
		.grid "map"se {
				ent;

		if (ce[0]lse {
			ns.sna"n,e,s,w,se,sw,ne,nw to be e.left > x1 && selectee.right < x2 && selecontainerOffset,
 y2);
ntainerOffset,
tee.bot el ).c

	_of				}
				}

	_) {

	tee.top > y1 && selectee.bo/ thatui. = t
			if (hit) {
				// SELECTt.positioselectee.selected) {
					selecte = false;
				}
				if (selectee.uis |

	stpare_MULTIPLY: 1css("mathat.si elem = $i-unsel},

	stop: functting");
			
		}
	},

	s(that.pos elem = $(that.pthat.size.height * th_size.heig/ UNSELse {
					s = true;
		tee.unselecting) {
					sel, ui ) {
				$( this ).addClass("ui-dialog-resizing");ry UIthat._blockFrames(ui.com
* Incltrigger( "://jqeStart", event, filteredUi(*! jQuui.com},ry Uwidget: function(uery.ui.! jQuery UIe.js, jquery.ui.widgetjquery.ui.mouse.js, jquery.ui.draggablestopquery.ui.droppable.js, jquery.opui.ds.height =  - v1.10.3on.js,y.ui.cor.accordiwidth jquery.ui.auui.buy.ui.cor - v1.10.3remove2013-05-03
* http://jqueryui.com
* Inclunudes: jquery.ui.core.js, jquery.ui.widget.jopjquery.ui.mouse.js, jquery.ui.drag
		})
		.css( "posiui.d", t-explod.ui.d},

	_minHn.js,query.ui.drQuery var .accord = v1.1..accord;

le.jturnld.js, jion.js, j== "auto" ?ry U.accordi-fade.js, :ry UMath.ui.(js, jquer-fade.js,,js, jquery.ui.ef.ui.effectt-explod jquery.ui.effect// Need to show the 
* htts, jgety.ui.actual offset iny.ui.s, jquerypluginect-folisVisiblequery.uiuiD* htt.is(":v.ui.slyui.coif ( !ery.ui.sliQuery Ur.js, jquery.uquery.ui.c jqur.js, jquery.ut-explod- v1.1i.effectdation an.ui.drry.ui.tabs.js, jquery.ui.tooltip.js
*hideyright 20effectsetOaccordquery.ui.drod.js, jqeffect-fol* Inquery.ugable.js, j = falsegable.js, ableeId = / = {}-high$.each$/;

// $,uery.ui.drokey, valu jquery.ui.IncliqueId = xtend( $.ui, {-highed MITkey jqudgetRelated.g., $.uQuery UIents withtruep.js, jquSPACE: 8,
		Cencies, e: 188,
		DELETE: 46,
		DOWN:s, e.g., $.[: 8,
] = $.ui,p.js, jquer-highry.uients wiquery.ui.too_dgety.ui.co1,
		Nation andright 201ry.uir.js, jquery.ui.spidata(uip://jqs, e)")jquery.ui.tooltip.js
*	PAGE_DOW( ".accor",		ESCAPE: 		DELETE:uid = 0,
	runiqueId = query.ui.droend( $.ui, {
	ver/*jshint maxcomplexity:15*/js, jquerDraggs, e, isRPAGE_DOWgable jquery.ider.js, jquery.110,
		NUM 8,
fect-
* htt2013-"VIDE: 11 jquery.ry UIquery.ui.dial other contribs.each(func ry.u		3 - 2013-0ode: {
		Bht 2number" ?
				this.ss, edtion() {
light.						if 1,
		NUupy.uikeyCode: {
		BACKber" ?
				thiappendTotion() {
r.js, jquery.urguments		setTi_s ),

	scy.ui.dr		if ( fn ) {
					buttonction() {
ollParcreateB;
		ifD_MULTIPig.apply( this, acloseText );
		};
	})( $.fn.focTitlebarCitio.t;
		i(ery UI// Ensureexist we always p {
	a strinr elemlabel: "" + $.ui,.drag
						if ( fn ) {
						functiontion() {
rn function = UMPAD_SUBTRACT: 109,
	)/).test()yui.comry.uis,"position"&& !$.ui, {
	vers
					var.)/).test(("destroyyui.com).tesed MIT */w")+$.css(thi,"overflow-y")ollParmake functiony.ui.co jque.test(this.css("pot-explodeVIDE: 111,
		N
		NUMPAD_MULTIPig.apply( this, a	PAGE_DOW).test($.// currently		ESCAPE: , becoming non		PAGE_DOW(this,lay, fn )")) && (/(auto|scroll)/).	PAGE_DOWN: his,"overflo.test(thisthis,"overflow-y")+$.css(thERIOD: 190ow-x"));
			}).eq(0);"overflow-x"));
			}).eqchang		}
handlesngth ? $(document) : scrtypeoflParentfect-filter).test($.

	zIndex: function(,
		RIGHT: "this.cs"Code: {
		B		if ( zIndex !== unde
		return (/feq(0);
		}
eturn (/fixed/y.ui.tacument) : scr$.ui, !=th no dt = this.parents().lay, fn )nction() {
				return (/(auto|tthis).test($.css(thbehav06,
		NUMPAD_SU(this.cs.find(".-03
* httpbehavition() {
0,
	runi, jquery.ui.drct-transfIfy.ui.user hasMPAD_DIdy.ui.menu.j,y.ui.		// WebKi and );
				if -contenttransfdivs will both have ui.but poson.js, set, so = tnr.js, jrejs, themect-folnonCbsolut-shake.jmien zIndex is not axn zIndex is no.js, jqueryquery.ui.effect-high// R// IEabsolutCOMMter(fuotherelement* Copyri.effery Uui.bu:t-pulsaretur-fade.js, j0iv styaxde.js, j"nonediv ston.js,: 0MAL: 110,
		NUMy.ui.effectWi.but>	value = ui.butQuery U jquery.ui.buttovalue = parseInt						if //		// IEwrrgumrsted eleme|| peterminPareesition =of all			}

		re of nesith an ss( when zIndex is nider.js, jquery.u value ofx: 0;"></	// <div stf 0
				lem.css( "zInrn (/(( el.outerde.js,D_MULT specified
					 = .js, jax( 0.js, jquerui.effect-- when zIndex is n
		TAB other browsers 40,
}

		imoveUniqu"><div shis.lenumberate.js,	});
	},

	removeUniqutest( thition() {
		return th-scalele="z-div>
					value = y.ui.effect-pulsat($.ui.ie && ith an e {
		retur-fade.js, j specified
					/me, im"><div sty other browsers returrn this.each(furn (/(relat eue wNaN ) {
	var map, tocompl		});
	},

	removeUniqon.js, 
		});
	}
});

// sel
						if ( fn,
		NUMPAD_SUBTRACT: 109,
		PAGE_DOWN: 34,
		PAGE_UP: 33,
		PERIOD: 190,
		RIGHT: "-fade.js,"tionrentsfade.js,ction() {
0,
	runudes: jquere element is posirn !!ifjqueBdes:ring
				docu an eers
	 "utton|" ).map(element is posi, jquetton| jquery.ui.a	BACKSlight.j$( "<div>"						$( {
		retur	.js, jquer"absolutz-indexon() {
		ame ?
this.iseInt()ment an: 0;"></f its ancest&& visib				${
					us ),

	scrf its apalow-ctio				$(on.js,element )r.filte) )[0]					.ui.effect jquery.ui.efe element is posi: 106,
		Nutton|object/($.ui.ie && utton|objectquery.uy.ui.codeletParethis, "visibili( img );
	}
	allowIntera.ui.dquery.ui.droppablrn $.csindex$roppabl.tarquer).sitiost				// WebKi").lengIndex" ), light.j
		END: alue !==TODO: Rery.u hack when datepicker iion(}

		ret//ion" );
	front logic (#8989n $.light.j!!expr.createPseudo(function( dataName );
	) {
			reui.effect/(statOverlay).addBack().filter(fun!other contribmodal( elem );
						}
					ht exist from componwidgetFullN ?
				}).l" ) ) );
	},

ensed MIT $.uiunction.oble: fInstanceurn $.css// PreatePsuseem = nchors| posinputs.t, "tabW			po a dela,
		Ccas				}
dex = $ is /(statd from a.js,"tabeatePsrent = 're go		}
to be cr( elling. (#2804n $.ern !!ireturled :
		"a" === n"tabHhis.c unction()( 1 ).jqositio") (#4065n $.ex
		$.e		var tabIndex = $.attr( element, ": jQuernodeName b ?
		!focusinunction"| {};

$.exteatePseudo ?", "Heigh!ion: "r[ ":" ], {
	dat"Top", "BoBottom" ],	r.creapndex" Defaulplete.js,picke			// WebKiinner.js:lastsition === "absolut"n $.exion v: 109ive" ) );
	},

	)._ [ "LTabb by the bro: 35,
		 (/(rela 35,
		/(relative|other NaN || jquexNotNaN
				t - 2013-05-03
				o-er, margrt: jQue$.each(  ),

	scrollParent: function() rn !!iand other ble: f,arentNmousedow
		/_keepF[ "L		ma/(relat" ], function( i, name ) {
++ui.effectw-x"));able: function( element ) {
		return focusable( element, !isNaN( $.attr: 106,
		Ner, margQuery Ut" ], function( i, name ) {
--	BACKSPACE:{
		var tabIndex = $.attr( element, "e = name === "Wunidth" ? [ "Left", "Rigrop.js, jqu, border, marty" ) === "hid border, marginnull( img );
: 110t" ], function( i, name ) {
	= 0;

// DEPRECATED
Height" ]BackCompato a value where //.ui.progre.accor wi || rra[ 0 taui.dr" + justexNaNridtiveth old		};
		})  ) {
		$le: fun( "	var tabI",ht" ], functer ) {e.js, jquery.ui.effect-trar( elutors; Liuery.ui.effectdation andeName.yA{
		[]deNameon.js, = [
	re0  this, ery.ui.sl	BACKSPACE:utors; Licuery UI: 106,}

		ih(functions.length ) {|| (uery <1.8
if ( !$.fn.object" scr"0" jqu	});
}

// Bottom" ] reducet-explod.split ?r == null ?
			(" ") : [r == null[0].js, jquer[1] ) &&
port: jQ red{
			ret$.fn1g = {
				i redor )=
// st ) &&
}).eq(0);ion
$.ui =[ "lefretu"t-cl ]| {};

$.exti,ion.js,P});
}

// supporort: jQ+//bug i7,
	=p://bug) ) { = {
				inon.js, ) ) {
.fn.removedth,
				a-b" ) ) {
a" ).removeDatat
			};

		functiondexNotNaN) &&
ui.p6.1, 1.6.:://bugs. + (tion( r0] < 0 ?ion.js, key: "+
						} else) + " " +eturn functio1.camelCase( 1ey ) );
			} el1e {
				return re1]st be vi	aCase/ sujoin thisquery.con reducqueryturn remove$.exte?
		{}ame ].call( t.protouery				$( this).css( js, jquery.ui.lement.parentN.userAgent.toL
$.support.selectstart = "onselectst ( elem.length) {
				query.ui.menu.js, jquery.ui.position.js, jquery.ui.progressbar.js, "px" );
	ider.js, jquery.ui.spinner.js, jqueed MIT */
(function( $, ui.tooltip.js
* Copyrightr" + name ]ery Foundation and t" in document.			event.preventDefault();
			});
	},

var uuid () {
		$.css}

}( jQuer			} {
	led :
		"a $, undefinedData 
-folrvertical = /up|size|ad.
	plu/,
	rt-explvemo remove: {
 ( $d: functi|horizontal/ {
		effects module.blindith -id-\d+$/;, don"outer" + C(stat}
		}

	
r( eleugin - v1.10.deNapropduce[ect-explode.j<a>" , "bottom ].p ( $( "<r
		retu"on.js, ] )ui.bu ).ds.eaod
			e module ]setMode( el, oablee ) {"
var" i ] |dire.ui.dey ). instance.ar iupdiv sad.
	pluginead.
	plu.ttion  instance.i ] |ref
		Nd.
	plug? );
			}
 {
	,
		caode || 2instance.elemena>" ):ption, s funcance.plmodule, option,ement[ 0 ].parentNode anim ) {
ui.po}

	query= nctions.lenhow	}

	
						, dittr( e// orgin{
		// i = eready{
					dtion" 
						's | []d.
	es are my,

	// oy. #6245
".ui-del) {
	retuui.s 			//module -
						xNot/ supp instance, av args) {
	retu,,

	/,
		TAment.parentn, the element migxtra content,
	el* Copyrigh
						rn( instance,/(statW						l: fdo(fvalue o ( bflow: i,
	den" $.ui.
	[ 1 ].ap =		}
		}
[ || i]D_MULy( instor arseFloat(		}
		}
i.effeeTypeis h||e( th ( instanclLeft"  =.bind(?][ 1 ].ap :e( t".ui-di		for (/ suppel				t.effetance.elemenush( [ turn[ i ] ] 0rn $.e cause this to hap		return;
			}abIndexNot doesn't{n this.un		// the ele : 110,
) {
			return 2true;
		}

	rollTop:		// TODO:+ly( instavigat// sjs,  at 0elem= thre.bindter(findexquery/ supplse;

		if ( el[lemenensed MIT  hich cases ac $, undefined ) {2ply( ins +		// TODO:		TAB: 9, 0;
		A insteverflow" .( inste( ( instancer ) {dur ) {
:lugi0, elem}

	easing; (ei]) !=}

	queujqueno depenction(the element is posi};
}

/ ][ 0 ] ],
				seData.celtend( $.ui, {
	/user wants restorhide it
		if ( $(5
		} catch( ery.u		return fals "hiddonr uuid = 0,a ==osit})( is decated. Use $.widget() extensions [ module ].prototyout" ) ?for ( i in set ) {
				= proto.plugins[ i ] || [];
				proto.plugins[ i ].push( [ option, set[ i ] ] );
			}
		},
		call: value;
	innersscalnction( instance, name, args ) {
			var imodule		set =
vart[ i ][ 0 ] ],
			ions[ set[ i ][ 0 ] ] ) {
				 instance.plugins[ name ];
			if (  "left" ) ?ugin1 ].apps ) imeduceo.= base|| 5ple widg) ) {
em =i ], nal{
	for ( vss( ( inring
 base* 2came
(func||  )[ 0? 1: detNode spr.js) {
	0, elem /{
	focompoi]) !=( elel; i++ )
		// utilorigde || ins[ 0 ].parent0 ] ]upk ) {= $[ namespace size/bug
				return;
			}

			for ( i] = $[ namespace ] || {};
	existingCons ( $(tNode iype upData( !prownlement
		// wtivell ) {
					/-assemisab		}

		trs, jqtelemourctor for pl) {
	lacfixed
		if=migh
		trust be
		trleop",
		trh[ 3 ] );cleanDvoijs, uch( elopacitydexNth: $.f clearType| posPNG issue ) {
IEry );

(functoLowerCugs.jq| [];.push] ), witho[ "inn$.clser wants to hide it
		if ( $( ).css( "ove== "hidden") {
			return fals; proto.plug		retur// allets (#8		// TODO:fo exie BIGGEST totype,isbIndexis.i D// TODO:/ 3ermine wa = $.clear i = "left" ) ?ellLeft"akes b>" )? "his.id = "u// ifncestors m"" : ies
asses a );

(function(  {
		// ui.pon witho: 1 ec( n {
		// turn true; > 0 ce.elem
});

})( jQue, conceon withou0| pos/ IE re initialn this.un
		// supn dostruc"first"{
	for ( v actui.effece alwayslement doesn't ) {

		for (? - existing ][ > 0 );
		el ][ .each( lems ) { {
		// a ction, ata( elpasses a
		return ha,
		smallese existing t later
		hidQuery );

" keyword ( existingCo existing/		});
pow( 2,pr[ ":"-ery asses athe prototypec(  to
		// redefine the;
	/Botypes up/ {
	/ ( $/[ i ] inherbelemto 0 --pr[ ":" ][ ateWidget )h $.cs here
	cons( ine th i <pr[ ":; i++ructor, elemeui.posiop, valuturn true;" );widget "-=// if+=/bugata = $.cl{
			elors: []
	} elemenPrototype = new ructors: []
	});

	basePrototype = new banew instance
	owerCasnherits from it
		_child/ 2 ] = 0;
		LHeigng from, datHrty directly on the new, value ) e in case 0e needf ( !$.isFunction( value ) ) {
			proxiedPrototype[ prop ] = value;
			return;
		}
		proxieasses anstructoraddBack().filter(fun" keyword (ery.com/ticket jqu		} catch( e ) {}
	}
	_cleanData( lems );
};

$.widget = function( ame, base: 110,e.elnn( s= elem.paateWidget )wef ( ty
		trjs, jbe this  jqul}
		(afatic"inprogressw.]+indexions, ele> 1uctor,nt );
?
		ce( $.lysuperApdeNam[ 1margi.conc= faApply;

				superApply,turn ! +ery 1odeNam el ).deructor(otype ) {
	var fullName, existingConstructor, constructor, baseProclip,
		// proxiedPrototype alproto.plugins[ i ] = proto.plugins[ i ] || [];
				proto.plugins[ i ].push( [ option, set[ i ] ] );
			}
		},
		call: function( instance, name, args ) {
			var i,
				set =it( "." )[ 1 ];
	fullName = namespace + "-" + name;

	tance.elif ( !sete
	//$[ namespace e: namespace,ts with
		wiment[ 0 ].parentNode.nodturn removelName
	}		return;
			}

	( instance.options	set[ i ]rs: []
 ][ 1 ].ap	// allSlati& Show args)
		if ( arguments.length ) {
			this.e prefix, e.
	// exterflow" ) === "hidden") {
			return false;
		}

		var scroll = ( a && a =o find w col: f[0].tag},

	tect-IMGtor = 
						r: eh(fu existingCors: []
[COMMA" : "s it anhiftct used to create rs: []
 causef th

var uui{
			var chilselectstarase.prototypw base();
	/to.plugData =
		rOon( s:0 ) {
			retuildConue;
		}

		// TODO: deter) {
			retuturn remo originally0n base.prototype[cleanData = fu{
			varelems ) {
	for ( var i =
		try {
			$( e 0, elem; (elem = elems[i]) != null; i++ ) {lem ).triggerHandler( "remove" !(function( uery.com/ticket/8235
		} catch( e ) {}
	}
	_cleanData( elems );
};

$.widget = function( name, base, prototype ) {
	var fullName, existingConstructor, constructor, baseProdro the name + a colon as theallows the provided prototype to remain unmodified
		// so that it can be used asget is
		/efix: existingConstructor ? basePrototype.widgetEventPrefix : name
	}, proxiedPrototype, {
		constructor: constructor,
		namespac;
			}

	space ] = $[ namespace ] || {};
	existingConstructor = $[ namespace ][ name ];
	constructor = $[ namespace ][ name ] = function(e
	}pos// ifneged to find all wid ), 10n case 
		}

	e() ]rApp( !prototypet( childd ( t args)
		if ( arguments.length ) {
			this._createWidget( options, element=== "left" ) ?{
		protot"newnstructor, {
		version: prototypeversion,
		// cop elem y thet( c );

(function( tually causedget is
		// redefined after a widgeauto|scrersiinherits f> 0 );
		el base();
	/g the sam0 ) {
			return true;llName.e.jsn( value  by referencproxi: ) {
	electoet;
};

$.widget.bridg {
			proxied)call(lainObject( valuata = fu ] = value;
 + childPrototype.widgetName, constructor, child._proto );
		});
		// remove the list of existing child co);
		// http://bugs.jquery.com/ticket/8235
		} catch( e ) {}
	}
	_cleanData( elems );
};

$.widget = function( name, base, prototpe ) {
	var fullName, existingConstructor, constructor, baseProexplction(structor );
	}

	$.widget.brrowse;
		pierom
?		});
rou?
		.js, sqrt(ach(functiect.: 3emoveellducethis!$.dato.plugins[ i ] |or ? basePrototype.widgetEventPrefix : name
	}, proxiedPrototype, {
		constr widgequery posinhernner.
	$[ n = ( ad to ith an  beforN );lculatationn.js,

		ize, tru ).css( "i.effec " +
					 slic= ( a  )ble( elemallow inse" || position =m =  (functs ti.butto.js, ceill: funcestors mus / .data(( "." ;
			}
		});
no such method&& visibl/	this.i ] ||functice( th widgeloopoptio j,  ( $, top, mx, myt( chilchildrenructor._ lem ).tri
	for ( i 				if
		}).tris posiodValuode aboment.hre;
		};
	ned ) pport: jQuethis.*ions + "				//rs: Value !== nData;
$.cleancl ) {ted to call cons$.uithodaliza.dat.;
	$.ach( prototypthis.nction( p );
===>s ) ( con
				}.			t+ i *sition is.eargini -NUMPhis.optioe thear _s			rej( protj <ions + ; j		});
		} |||s fr ( $this.each(.opti+ j *arAt( ta( emx = jnce =.data(ta( this, fullN prefix, e.amethodVoed
			nowsupetempmain to call rent =on ==e / the elhe new veed, fullN !==in a {
		$.eadivis.eValue-.opti pos-			teqsitiis.bldCo( ruur methody = th
			isTthod;
}

fu( $.css( el"bodys p				$(
			dexNotNa</otNaN :
			isTabIndexNotNaN) &&
		// the element an " +
						nction(llement an ( $: -} )._initment ans, jq-on() {
			}

funct, fullNseletur}
			}
		}
 - s(). itypeof scrolthis )  posurn returnValue;
	} based  case;

$.ns )bIndexrigielecwas locex >=+, element+*/ ) {};
$.Wi to mget._chonstructor	) {
	retu				$( elem ).fo5-03
		//If 	optionN :
			isTabIndexNotNaN) &&
		// the element an	var scroll = ( a ment and all oks
		create:: 0;"></r " + lse,

		// cans || {return t mx )._init() ] = funte: nullfunctierable = $yn() {
			s.focusable = Clone objects
	 bas1}

funcpace + ".Data.ca);
		this.hoverable =  bas$();
		thisst be vi $();

		if ( elemenement !== this )		$.data( element, this.			if ( ( $.elem ) {
		r $.W0	rem fullNammethodValue !=eanData;
$.clance && m				returnValudget.exsTabIndexNons: {
		disabled: fa( $.css( e= method jquery.ulue.puld constructors frthis._superApply,= _superAp
			$.widget.extend.apply( null, [ options ].concat(args) ) :
		faons;

		if ( isMethodCall )llows the provided protnction( instance, name, args ) {
			var itoggavior.ext ] = value;}

		Clone ob);
	 );
ototype.widgetName, constructor, child._proto );
		});
		// remove the listame,gth ?
			$.win is deprndow = $( this.document[0].defaultView || this.doc"num;

		if ( isMethodCall ) {prefix, e.g., draggable:start
		// don't prefix for widgets that aren't DOM-based
		widgetEventPrefix: existingConstructor ? basePrototype.widgetEventPrefix : name
	}, proxiedPrototype, {
		construc )[ 0 ];

	name = name.splis witho.e )
	|| 15idgetercall = /([0-9]+)%/.execildPro "' for	proF
				= !!o./ticket/94ets ti.buet/9413
query a vremoveData( $.|| inscamelCase( ?			pntNode.ent[ 0 ].pe {
[lice.call( arguments, 1 m ) {
		r elem ) {
		ret2					set[ i ][ 1 ].app to find all1e.options( instancpe =positill of them so that they inherit from
	// the new version of this widget. We're essentially trying to replace one
	// level in the proto "left" ) ? widget()
		
ue;
nction( ry.ui.d,moveClasstocompleNamee.removeClasstocompletate-focusry.ui.d		rets
				ry <1.6.t withe )
			
			hInt this.ele[ 1).da10 this100 */ redefin[ction() widge		ret}ry );

(function( $, undefined /ticket/941?				//: 0;"></dindex" )				e )
eleme:== 0 ) {
			//dProte of 0
				div></di
$.cleanData =lue;
				}
			1lLeft[argi/ originally used, b	}

		ternalnherit from 2 );
		}1
		if ( typeof key === "d ke detecleanData = function(y.uie;
	$.fn[ name ] =1, lem = ele
			});
	etNams = {};
			parts = 2ey.split( "." );
			| {};

$.exr( "remove" uperApply = ery.com/ticket/8235
		} catch( e ) {}
	}
	_cleanData( elems );
};

$.widget = function( name, base, ype, {
		// TODO: remove support for widgetEventPrefix
		// always highl.js, jq0].parentWindow );
		}

		thieue )lugins[ i ] || [];
				p $.wg				vImagry.ue === undefColorvar input = ll: function( instance, name, argsem ) {
			var i ) {
			if ( instance.opis.d					return curch( emi.effec					return curOetNamositiinde);
		// http://ild ) {
			vt.len withou inheres args)
		if ( argume[ kera contenta()eturn* Copyry.ui.effvalue;
			} elsined tyle="z-index;
			} else {
			o.c cur ];
	#ffff99rDocumy = parts.shift();
			er ) {

		try {
			$( econstructor, child._proto[i]) != null; i++ ) {ve the list of existing chill( arguments, 1 ),
			returnVs[ ketend( $.ui, 

		fun		} catch( e ) {}
	}
		}
				optio	thiame, base,CIMAL: 11pe ) {
	var fullName, existingConstructor, constructor, basePropuls chain parts[ i ] ];
				}
				key = parts.pop();
			undefined ? null : curOption[ key ];
				}
				curOptithis.widgetName )
			.removeData( this.widgetFullNamehow )[ 0 ]llName.toL);
		// http://buior to initiationrperty d leavest( optio"rHeiwidget in casen
	$.exp(			v	base = $.W )dConst	if ( eleowerCase() ] = funria-disabled" )
			.remourn !!$.drs: []
Toull  options,
				emtructor( options, element );
		}

	 opti	return tuse "new!, elei.spinner.js, t withinf ( valueget is
		// r* Copyrighton( suppress1widget.extn
	$.eoptin withoureateEvat( 	$.each( p1ototypurn !nction( propablede, this );
		nit();
	}on( suppr ( $.y.split( "." );
			kents
		if ( typeof -
			suppre	var __su= suppressDisabedCheck;
			suppressisabledCheck = false;tions[ keper = this._super,
					__superApply = thvalue ) {
		tment || elementructorsW( this, argumeup "urn !widget in cs,ed" ) {
				puIE ret nex, jquery.
		trhis._superApply = _ment;
	Apply;

				return returnValue;
			};
		})();
	});
	constructor.prototype = $.widget.extend( bemasePrototypes.widget()
				.toggleClass( this.widgetFullName + "-disabled ui-f inssabled", !!value )
				.attr( "aria-disabled", value );
			this.hoverable.removeClass( "ui,
				set = )[ 0 ];

	name = name.splry <1.6.3
tion: funco.ry <1.6alue ) ort: Disabfacto ) =ry <1.6.{
		v haselement == value: 0;"></lement,

	widge of 0
				n han( "ui-stsableis.id = "uturn;
	his.id = "ui-		return (seIntf handler ==ors mus
					reoLowerCaseoCreatemodule: "sca false,
		try {
			$( ecume: elem: functi	},
	_ $.noop,
	_init: $sabledCheck $( erCasry <1.6.:$( this  0 ) !== "stsablelement =scale	return this.ehandler.t.href |* == trunction() {
			uid || hrn $.eroxy.guid || $urn ( typeof ;
			}

	urn ( typeo match = event.matchnce[ ha)\s*(.*)$/ ),
seInt(roxy.gui" ) {
	else {n hanmodule( o
		}osit[ module ].protots );
ve the unbind calls in 2.0
		// all event bindings should go throughn a stringoLowerCaseo dire() )o);
		this._trigger( "create", null, this._getCrme.split( "."edCheck &&
						( instance.options.di" ) (&
						( instance.options.Cons );
 basarguments, 1 ),
					part0e ) set = instance.plugins[ name ];
	== "teOpt;
			}bled";
			}his ).hasClass( "ui-state-disaed" ) ) ) {
					return				}
				return ( typeof haler === "string" ? instance[ handr ] : handler )
	ed === true |valuey> 0 nstance. a v"		proto = ace;(|
							$( ttanc1		retxce, arguments );
e: namespnstance = this;
		retu
					re
			//;

}TabIndexNents(v1.10moduled++;
		this.e argume
 10 );
		overabl.lenuery;add( elemeledCheck{
			n( element,lem ).tre
	/onrom it anetwith the  );
	}, pos e ) {}) :
	" );/== "ts
				);
		s );
me.split( inst.accordi );
	},

	);
	},|| ["midd fal"<1.6er"	retu.accordidClass( 0,
		END:$.cl.accordi 0 ) 		// 0 ) ) {
 i ][ 0 ] ] ) {
 === 0  {
			// don'
			retuName rn ( typeof  this.focunce[ ha0nt, ance.event
		}
.accorditresst ) {
		thisguid || handlerProxy.gui.yfocusable =;
			}

			var match = .xthis.focusable.ad)\s*(.*)$/ ),
				eventName =entTar[1] + instance.eventNamespace,
				selec.x
ble: funcFumenunctionis.bupport // -ts
							}
		})umeneudo ?
		$.);
		// ht				curx" ), 10 );
		 0 )ined ? null : tmoveClass(toined ? nullf suTIPLY: 106);
		// http://bugs.jqucallback = this.options {};
e ];

		data = data ||[ typa;
$.cleanData = fuel		if ( selE: 32,
		Tor ) {
				delegateElems with he unbind calls in 2.0
		// all event bindings;
			}

,nt ||s._s,oxy.guid ||oto.plugins[ i ] || [];0
				proto.plugins[ i ].push( [ option, set[ i ] ] )bind( this.eventar iement =Option[ key ] == widgeAis.par e ) {}d (the c"-di
};

$.widget.extend = function( target ) {
	var i event
		orig = event.originaCopy) :
				if ( d (the cpe =.unbind( this.eventent ) ) {
		ll: fucP [];
				pfontSueryll: fuv		return !(borderTop
		// ].pus			cBsh( [back.applpadty dT i ].p event s.elemction( hallback ) &&
			cLeftback.apply( thiR.js,ent[0], [ event fauloncat( data
	}
}ent.originaventtype ).6)
		proxiedPrototype = {},
		namespace = name.split( "." "ui-stateo( "ui-sta this;
	
			mouseleahoverent.deloement.dte( eventName );
	},

	_delay: even" )[urrent Target )ll: futurn remove this widscroll)/).t ] || [];
		dClass( ?
				o basig ) {		rezern: funct) {
			// don't return don't.focusable.add( ellement );
		this				return {
		var prop, orig,
		 ).css( "ove}
 ).hasClass( "uidelay ) {
		function hadlerProxy() {
			retun ( typeof handler === "string" ?instance[ handler ] : handlerions.effec {
			vr, {
		teEven&&ct || defaultEffect;
		opt);
	},

	toof ons ={};
elsin: f
	_focusab;
			}

ent, but the uns.delay ) {_focusable: function( elemenns ==on( element, {
elay( optio
			elthis.eventNamespace;fectName ] ) {
			el$.cleanvent) {
 thi	selecto	.apply( instlerProinstancens.delat.href |/ent ) {
				$( ern setTiName ]( rn $.e.duration, his.id ))
			tont[ effectNamto options.duration, options.easing, ctoack );
		} else {
			elemen-focus" );Sent.d,
		css boxts
					ent.dearentoxk ) {		next();
			// c.widgefuncVd.
	plug| [];
lement[indow orui-state ]( yo a val-statto.			}
			}| [];
		the co	})();
	callbac === "ns.delay ) instance, naTranion and e itcallbaet on theHandle,g, callbeHandled =in: f
});

$.widget("ui.mouse", {
	version: "1.10( dooptiotlecto {
				retH	proto = ( $, undefined ) {

var mouseHandlxd = false;
$( dxcument ).mouseup( function() {e ||
		Handled = false;
});

$.widget("ui.mouse", {
e ||
	on: "1.10.3",
xoptions: {
		cancel: "input,textarea,button,select,opvent);
			})
		
			1,
		delay: 0
	 method &lement[ 0 bsolute"	}
				next();
	erWidth:;
		}
	};
});

})( jQuery );

(function( $, undefined ) {

var mouseHandled = false;
$( document ).mouseup( function() {
		retu)ction() {	}
		}
Handled = false;
});

$.widget("ui.mouse", {

		reton: "1.10.3",
	options: {
		cancel: "input,textarea,button,select,oping one instancce: 1,
		delay: 0
	ses args)
		if ( arguments.length ) {
			this._createWidget( options, elementventPCheck f event
		or[options] .effetions: {
		( value ) ) {inde				$();
		} C" + opte the targ s fun met("mouseupinput,textargetBhe targ(y );
		,me ] ) {
			elemene ]( 			thi);
		}
	*)$/ ),
				eve-andler === "strin() {
the targ.yown: functio.optiont) {
		// don't seInt(e than one '" + opandle mouseStx	element[on(event) {
		// don't let more thaata  ),
				eveandle mouseStart
		iftouseHandled ) { return; }

		// we mouseUp(eseInt(d mouseup (out of el ).."+this.widgetN );
/ IE op &this. { bar: ___ } }kEvent")) {
					$.removeData(event.target, that );
ame + ".pre;
				}
riginalddcroll ]s/ $.i- a refecallback callbaction() ons ( ins].concat.targ ) ) === 	});

		t
		retay: 0e ||
			ee ||
	me ? $(event.targw: "fadet.targde: "faday: 0	}
		}

		methoction() callba	});

		te ||
	var _s fun ?
		!*[ retu]ns] $.ui =			curOpti this.eachis.oparts.pop();
					c_).hasClass( "ui-f ( typeofhis.oed" ) ) ) {
		"number" ction(			}
				retns = { durationction(er === "string" ? 	hasOptions = elay);
		}
handler )
exec( nainde e ) {}Query UI , the element his.o}
				o2e ( elem.lenction();
	},
	return this.eeDelayTime			$( event.curre.3",
	oction() {
		s._mouseSta"ui-state-focus	.bind(vent.match( /^(\w+s._mouseStaction( event ) {
				t.preventDefuseDistanceMeer have fired (Class( "ui-stat	.bind" ) { typection(in: funct		if (!this._mouseStarted) {
				evence: 1ntDefault();
				return true;
			}
		}(evenClick event may never have fi this.widgetName + ".preve= $.data(event.target, }

		// these delegtClickEs )[ 0 function( $, undefined )  {

var mouseHandled = false;
$( document )ent) !== false
});

$.widget("ui.mouse"d = (th	version: "1.10.3",
	opnt) !== fal === "dt")) {
			$
		};
		$(document)
			.bind("mousemove."+this.ce: 1,t")) {
		e ( elem.lengthouseInit: function() {
		vaar that = this;

		this.element
			.bind("mouseUp(event);
		};
		$(document)
			.bind("movent);
			})
			.bind("me, this._mouseMoveDelegate)
			.bind("mouseup."+this.widgetNtrue === $.data(eventegate);

		event.preventlName | ];
				}
		veDeleg.effeme, this._mouseMoction(rs: []
	}t")) {
	troy();
				length ) {
				curOption >" ).outRClass( eturn this._
		NUMPADass( tion( key		} catch( e ) {}
	d = (this._momouseMovuce( elem, s.widget.extend( {|| name;
	$.fn[		btnCreateOptions: $.noop,
	_getCreateEventData: $.noop,
	_create: $.noop,
	_ini			curOption = option		btnIsed ? nul + " ugs.jquery.Check flag, shuf functioeUp(evenicket/8235
ifarguments, 1 ),
			returnValue = this;

		// allow multiple hashes to be passed index his._mouseDi>" ).outd" ) {
				'" + opty str newhe new ve thit || thi;
		theHandled =
		};
	});
}

/$.fn.adaticnction( key, sTabIndexNottNaN) &&
		/r 188ivlement anunctiowindow)
	s._mouse	// canEvent = equery.ct(event)reateElementon
$.ui [ns[ i ].pnction.data( "a-b", dxart" Data = (funturn !thpo|| {};

$.ext_,.filData = (func-folvlass(tion: funcstralue )s._mouseseDoR| insidx ?ata(event.tach( ndow)
	{
				rewidget lelement = $( 	// <di res.widgetNaptionew set to 0 ) 
			option" ).data( },

isTabIndexNotNaN )nce
	ction( eh.max+ "pxon( 			};

	Met: funlight.jMet:+

	_mouseDelayMet: fu this.wi, this.wid( elem.lenlems );
};

$.widget = function( name, base, prototype ) {
	var fullName, existingConstructor, constructor, baseProsh) {
;

		if ( isMethodCall ) {
			oto.plugins[ i ] || [];
				proto.plugins[ i ].push( [ option, set[ i ] ] );
			}
		},
		call: function( instance, name, args ) {
			var ime.split( "."putIndex < inputLength; inputIndex++ end( {}, target[ key ], v2 this= base;
		base = $.e = $n
	$.expr[ ":" ][ fuoptioction( e() {
				valem ) {
		/urn !Node || ins(= $[ namespace ] || {};
	existingConstructr = $[ namespace ][ naodule, oMe ];
	contructor = $[ namespace ][ name ] = functio		if ( instance.optionsllName + "-disabled " +
				"ui-st optionlow instantiation without "new" keyword
		if ( !this._createWidget ) {
			return new constructor( options, element );
		}

		// 
					target[ key ] = $.isPlainObject( target[ key ] ) ?
						$.widget.extey ] = value;
				}
			}
		}
	}
	ree,
		handle: fa) ) {
			proxiedPrototype[ phis.options );
	null,
		stop: null
	},
	_e = function(ata = $.cle*ype[ 		// handle nesnull,
		stop: null
	},
	_create: function() {

	is.eleetFullName || name;
	$.fn[ name ] = frototyp= false;
		}ctors, akruct{
			handlers = ee, function( propname;
	$.fn[ name ] =1s){
			this.element parts.shift();
			if {
			this.element.a,
			/ions = {};
			parts = key{
			this.elemention( options ) {
		var kction(move		this.element.er = this._super,
			ll( arguments, 1 ),
			returnValue = this;

		// allow multiple hashes to be passed on init
		options = !isMethodCall && args.lent.addCla		returnValue = value.apply( this, arguments );

				this._super = __super;
				this._superApply = __superApply;

				return returnValue;
			};
		})();
	});
	constructor.prototype = $.widget.extend( basePrototype, {
		// TODO: remove support for widgetEventPrefix
		// always sl)[ 0 ]event may come from any element
		// so we needoto.plugins[ i ] || [];
				proto.plugins[ i ].push( [ option, set[ i ] ] )bind( this.eventNa: function( instance, name, args ) {
			var i-state-hover" );
			this.focusable.reputIndex < inputLength; inputIndex++ ) {
		finment: false,
		cursor: "auto",
		cursorAt: false,
		grid: false,
		handle: false,
		helper: "original",
		iframeFix: false,
		o		prototype ( instance.optct( value ) ) {
					target[ key ] = $.isPlainObject( tarend( {}, target[ key ], value ) :
						// Don't extend st.version,
		// copc. withMode: "both",
") {
			return false;
		}

		var scroll = ( a && a ===
						$.widget.exined after 	stop: null
	},
	(isNaN( existiney ) -
			
				} elseinherits tanc {
					target[ key ] = value;
				}
			}
		}
	}
	return target=== "original" && !(/^(?:r|an( name,	stop: null
	},
	_create: fu)t.prototype.widgetFullName || name;
	$.fn[ name ] = function( options ) {
		var isMethodCall = typeof options === "string",
			args = slice.call( arguments, 1 ),
			returnValue = this;

		// allow multiple hashes to be passed on init
		options = !isMethodCall && args.length ?
			$.widget.extend.apply( null, [ options ].concat(args) ) :
		t("uif" ) = parts[ i ] ];
				}
				key = parts.pop();
			tePseudarts.thod ffset = thiFixon( etePseui.effect-explodepace + "femenback 	widgin ) 	widg	retufixT( conthis.element? 	wid.scrollTop(tanc	handlixfaultop,
			left: this.offset.lefaul- this.marendmoveData(.offset()) || optiotion[ key ] = valueDownEvet.scroll on(ev- rgins.tates 		$.dat { //Where (thisclicks.lefindex: 0;"></ffset()inn=== "string" ?  returnX - this.off: handler )
			retur.scroll = fhandle	$.extend(t absolute pndexNotN c013-='this.uuid;
 absolut'widgetEventPre( $.css( elme === "Wiffse.each( side, fus;
	013-Heightssible to
ault(), jqp
			},
			pa the click happenedd, relay used for relelement
				left: ev-state-disables.offset.left,
							return;
	geY - this.oates a	// set th,
			left: thi	top: t fun/ the eled ) {
				te;
	$.fn[ name ] = fhis._mouseDrag(event);
			return ev (Matabsolutty" ) === "hiddisabled" ) {y() {
				// allow widgets to customize the disablen orig[ "outmenu"Creatverset the1.10.3backets (#8E
		}) : "<ul>contai: fun3 thistype ).nt[ efictContainm	sub(o.c:+ thient(-carat-1-erDocu}

		enus evespace,	// set th ) {
	disa(thist-cli?
			ven e.optioop	if(this.rolions(o.cur
		revecall $.wis.wilur:.eachs.mar[ "Lthis._cachion( othis._;
		},

	focusnput|select|textarea|b{
	dveMenuon() {
	ith an yMet// flag),
	t = dendex" )filtert( optioclick this.cctorssDir any tializbubblom
	b through nesx >=_trigs ) ) ||					erWidtove tseenterents with an is a uniqueId this.idgetName + thi_trie -=
				orect positt();
			}ui-corner-all possiblcomplegetting its cort + ce;
	!	returith an et = !th		//ent(ns] 
			retu beforttrlation 
		}

other contrib
		}ates atabIndex	this.d{
				eturn this.catch= eleets(ts);

		fn.cal/Exec
		returot of ui.slivent, tr_his )[Width" ?ets(t
			t dragry.u},

spaappl$t.sex8
if ( !$( Top", "Bottom" this ) ) ||eout(func: functtion( key,erWidth: $.fn.innerWidth,
	hese aturn ! ) 110,
		NUMtion === "fixed" ) {
			this.he helper not tofore getting its._moeinhen.call( 				$( .ddm "ariavent);
		tins[ruventDa}
					}, deln"))) {
tabindex" )eHelppageY stickbIndexNlink ) {s= "s correr = _ets(tter(fun// supm (and cashouldthis.parstay);

ULy.sp		}

avigundef)N( ta"					size );
	the matem > a"a: $.expr.createPseudo ?offset.parent = this._getPareggableessary );
	ion(event);
		g", event, ui) === false) {
				this._mouseUp({});
				return false;
			}
	gger("dra:has(a)event, ui) === false) {
			ht ex= this.posir.createPseudo(functionrted (gger("dra[ "inner ) {
		returrag once - th;
		fset()nos.axis |ion(event);
		thi{
			return fune the drag once - this
		ENDable = }).lion( os.offsetPyMet: f// OptioTrigger);

ets(t	);
	};
}

[0].sty.helaxis || thop+"px";
		}
		if($.is.posixpa?
		event);
		}

	dgetNam) {
		returith an el, a :eHelpw is hiddent.preven, argu and ca++;
		tion(eventhis.position =.jquery.ui.
			dr, [c. wit].href ||

		rened
			$.ui.d "dragiuseDr,
		workleve itls, jtion) {$.ui.dN( ta

		rettherwismentlunstrucevent);
		}
sins hat

		no longer; " +
lortable)
Compute thevent);;
				//if the) {
	reent) {

		//If we are ujQuery 1.6.1, 1.	ing foimeoutpute thr[ "r);
		}

	

		functParentOffset();
			if(rget  "y") {
			thir[0].style.left = this.position.left+"px";
		}
		erflow-TePseudoyMet: revendata(= this.poevent);tual allbackiype;gtOption( newl proc
			$se;
	"dra
		}

		to aw insa jump ca
			$by adja<1.6.
		}

		=== "relaprepw ob== "( size y( thiragStarset()id" && !!$.i		if ( top = this.po$.ui.dop+"= this;
					osition(evelement, i.com
* is.his.oroppable.tePseudo		return fa					s._se:			$llapseAlspace,
			if(s._se "y") {
	trinDuration, 10), fueHelperery.ui.droppable.parsA.ui.dIdrag"<a>" ).outned
		re'sment, argan		dropped = 		thatropp$.ui.d	}
			});
	not,vent) ._mouseD

				tvert === jquedragtabbablevent);||er && !o.dropions.revert.call| this.optio.eq(
var us.axis !== that._clear();
				}
		(this.originalPositin faletParentOffsetons )
		thnt, ui) === false) {
			jQuery <1.8
if ( !$( "<a>" ).ner" + naevenain			setTiith an ilter = name === "f ( $.ui.d if givrig = {
				ithis.Duration, 1rm the manager ab}

func		return fakeysize -= pareturrDocumens the herefresi.dat] = ori	_mouseutting ) {
	se;
	Duration any optioExecute the d 0;
				ifnodeNameer ) {
ets(tis);
		});

		//If the ddmer" + n;
		}
		if(!this.options.axis || thop+"px";
		}
		if($.ui.dd			$.ui.ddmanager.dragStopction(/e the casr && ag once - thur) e ddmanagerag once - this causes tCIMAL: 11) ) || 0;
				ery.ui.effect-transfD-x")); (sub)Execute the dlper not to 

$.wiAionAbs = th$.ui.ddescendamove ( $.us started (his;

	}add	}); this.wiropped))) {
			$( correct position

		//If the ddmanager is useis.othe managerhis.posi	var o = this
		}moveAttr("id") : this.art(thismoveAttr("id") : this. = thtion(ledbdgetName: 	var o = this.opti, info		this.posi	var o = this.opti[options]0].parentNode : o.appendent);
		this.posi

$.wiUe visible bef] = valu(this, e

	_creaons.rever function(event)nction(o.helper)is.optio {

		var oes, inform the mae");
		}

		retur: this.element);

f(helper[0] !== this.element[0] &&ions.revert.at[0] && !(/(fixed|absolute)/).tropped))) {
			$( this.element.ion(evh				ent);

		if(!helper.parents("body").length) {
			helelement);

		if(!helper.pappendTaspop] ||:
			isTns.rever.delay;
		if (!thieData.ca		key = parts.pop();yMet: f this._handidth,orm the mTriggerallbacropped = falseerProive to the hel(this, evener.css("position"))divider			helper.css("position", "absoluick.topt, dropped))) {
			$(p;
		}
		if ion

		//If the dds passeffect		return  $.expr.createPseudo ?	focus: (function( orig20 {
		retumrue;}
		ev
			r {
	 i ]skiph.abgegates th: $.fn.inner		$.ui.ddmanance && mescapefocus();
rn function( $.ui,.re			re( /[\-\[\]{}()*+?.,\\\^$|#\s]/g, "\\$&ionTo("absolswiue;
;
		}
		keyC			vffsetPisTab$.fn.ed on s.PAGE_UPscalethis.t: fiousPagousevent);
		}
breakTo("ince the following happDOWNed:
		// 1emensition of the helper is absolute, so it's posHOMEed:
		// 1_nt ism this w, is a chil of the helper is absolute, so it's posENDal offset parent idisabnt",h mean of the helper is absolute, so it's posened:
		// 1. The poon of the helper is absolute, so it's pos is calculated bason of the helper is absolute, so it's posLEFTed:
		// 1Durationon of the helper is absolute, so it's posRIGHs.scro
		}

		//if the ori	retur elemenl, a ) {
ion(event);
		thif the ddmanage, inform the managerthis,t, and the scroll parent isn'TERdestince the followingSPACual offset pthis._clon of the helper is absolute, so it's posESCAPual offset ent[0] !== document && $.contaiets (#8scaleetParent and cach.find( thit: flse;
	},. The poFouse.of opayMet:ction() { = Silter.prevCharon scalculated on stayMet:		//fsetParentg")) {ue (see #8269)
		mouse.(see thisisabled" ame.toLower=t)) ev
				}
		 top: 0
		END: 35reateElementame.toLowerCarent+nt(this.of ( elem.lent theis.eew RegExdget^
			ar po = ame.toLowe), "i.animatentOfflse;
	},

	_modmanevent) {
		//Remove frame helmouse.
if ( !$( "<a>" ).light.jt theement[  - v1.10.3) {
			obj = ob.trag
y.ui.draggetRelativeOff top: = crue;.ithis}

		//if the drag
llLe a v-1uid = 		if ($.ui.dd basddman//Remove frame heguid =lativeve(event)) {
	s("topetOp/if a erflow-.mouse.: 39,me wo ?
		rHeigame.toLowep
				};
};

$to data(this.r && !th		};
		};
			}
		}});
		returtion(thxist ction() { {
				eve("top"px";
		}
		if($ame.toLowerCase() === "html" && $.ui.ie)) {
			po = {arent.css("borderLeftWidth"),10) || 0)
		};

	},

	_getRellativeOffset: function() {

		if(this.cssPosition === "relative") {
			var p  = this.element.position();
			return {
				top: p.top - (p event);
+ (parseInseInt(this.element.csthis.originalPositiativeOons.axis !==seInt(this.eldings = th:
		// 1. The posetPare=arseInt(this.ofs = {
			top: po.totabbablr is used for droppables,dden";
		}).lHeight()
		};
f ("top"alueemenhis.widgetName + ".pptions;

		if ( !o.containment )hese areateElementptions;

		if ( !o.containment ) {
				returnetParent and caent
				erWidth: $.fn.innerWidth,nd( $.exprhis._cla: $.expr.createPseudo ?
		$.{
			po.left += this.scrollParent.scrollLeft();
		}

		//if the		return {
		[ft + this.mar='osit'ons.dperProportions = {
	.top += this.scrollPareateElementr.drag(this, event);
		}
{
	// $.cachseUp.ca jquery.ui.effect-fol_trigeChilent(on() {
				$( thent().Triggerable.jriggerring
				.css("positionreturn focusabrtionj) {
			tI/ tracquerue); //Execute roportio= "relat ":le.t.helper))is is a re getting its correct position

		//If the ddmanager is used for dr
var u( $.ui.ddmanager ) {
			$.ui.ddmanager.dragStappendTo));
	:tPositight() || docis.elemen: " causnt.pageX;
		$.ui 

		return this;
cument.bt) {
			this._mous	n false			rif ( bj = obeturn;
roportiClbac),
			respanaN :
			iefore getting its cor(see is.ont;
	
			nt;
	is.contaiportions.width - obj.right + ,e
		this._cn;
		}
elper.itionAbs = ththis.margrtPositionis.helppreumen( false;
 === A(this.elf ( tionAbs = thppendTo((o.his.pa( !ce ) icrollL - (parseis._trig.heldocumentadrgins.toto call j) {
			thon'taftep.ca li		}
		}	thiaton( e{
			if(tdapt	};
}cumentons.revert.his.containme
	_ad)s.helpersition minus the r;

	},

	_adjustOffstionAbselemencatt()
{
				r"string") {
			obj = obj.splibe visible bef				0,
				0,
		ger is used for d.ui.ddmanagertart(this, -urn seer ) {
			$.u_	_adRoy th in obj) {
			t
		if ( o.cunhe rptions.
	_adsrevenaiUpDel (seeexNaN/or dasthis.sly		}
ick.top = oarseInt( c.css( "paddingLeft" ), 10 ns.delay;ed :
		"a" === nodeNa falselick.left = th// hyphtotyemight", dataNsid ) index /[^\-\u2014.offs3ere ement[ 		ovep: p.top// support	overarseInt( c.cs
		//If the ddmanap;
		}
		if ("bD_DECIMAL: 110,
cancel  = this._conv tionibeturalidnyrag: function(}
		}

	arseInt( c.css( " = this.position.top+"tionAbs = this._convertPositionToositioned
			event);
		}
tionbees.elry.udopped) {
		ion(eves.offsetParent[0])) {m the manager that draggiarginiginal eleme	}

		 34,
		PAGE_UPd(th uuid = 0,
	runarseInt(mouseDrag(event) light.j ) {
	ger evment	_adjpened,istboxvers	RIGHTis.o[			$.ui.ddmanager.s,
			
		UP: 38
	}
});

// plugins
$.fn.extend({
ber" ?
				thi().removarentNode;
		mapNamsition", "absolutee #50 elem = this;
					setTimeout(fut ) - this.he						$( elem ).focus()), left : ight 2013 jQulay );
				}) :
				or] ) ) vent) !== false) {
					s.pareffect-folue); / $.es.optes the heion =uery.ui. of th&&
		}
		ir si{
			t			dropotype._mou_set.leIntoViewarseIntioned nodesevent);=rseIntthis lue.puhis.optise;
	},

	_mo		return {
				to_generatePosition(eve position
		ret10 )up( el 0 ) -ons,
			helper =he d	} else { 
		}out boo(this.drlaterssum( "ud cais managedent.p];
		 + this ) ) ||ffsetParent[ll
		if (!this.offsetionAbs = thons,
			helper = offset.reer = c.css( "overfl
	},
	_mo curOptioevent); {
	reion"))) {
,elemenyement to offsering" "." + this..options.axis || this.optioring") {
			obj =:this wi= [
				0,
				0,
		this.helper).animent;

								// Only for relativui.mouse(($.ui.ie && (/itiolue.pusent.parentNode;
if ( t
		var over, c, ce,
			o = thisders (offset + boset();
	.returnnTo("absolue); // parentvent) {
		//Remove paddingindexue); /th() - t&& ( /^	!!$( - ( parOnly for ress( "bordernodes: js, turning(ue); /eturn {
			top$.ui.ddmanageottom {
	retuoned nodes:iour) {
			droppedery.ui.{ion =:use po}ffect-slide Relative offsheight - objse position
			&
			call,  event ].c"a" ).re, set.leStar		}) -shake.jottode.js,nValue = rn !!ihasSet.lellParvalue;
			callp",
			has = fa$rn !thset: function()ilter&&
			callback.ascroll ] > [0].event ].cche the scroll
		if (!this.offset.scroll) { event ].co.scroll = {top
				}
		ottomis._getP the clset: function() 
		 * Constrain;

		//Cac- ? this.offo = { et.leOffset: function() set.left - andled ollParent,
Offset: function() tocomplete.js	pageX = eaining -tocompleteisabled" 
				}
 ) )this.scrollParagging yet, we won't : this	return rh - this.margi		if ( this.+		pageX = e >scrollParent,
ative_container ){
					co = this.relative_containere th.containmentnt = [
						scrollTop() + ( $(d(this);
		});

		//I,pageYeFloa).scrollTop() + co.top
					]tinue (see #8269)
		if ( this.o+$.css(thi{
			po.left( elem );
						}
					}, dep * mod -										// Tropped))) {
			$(this.hfset withoutnt to offset peach(fe" && !( this.scrold(thnt[ 0 ] !== docume			if(event. $.contains( 	o = this.oheight - obj, left : rt, sinue (see #8269)
		if ( this	( parseInt(f(thilement, argf(thitop:itioFirefox bugmargins || tha .5 pix);

	o iniif, jquery., left :turn remo, dat	!!$prepavenstrucllbac	}

	neratePon";

		per[ 0 ].parenoptions]ptionsositionelem );
						}
					}, des.cssPosition === "fixed" ? -this.scollParent.scrollLe.is(".upvert		}

		return t() : this.offset ) ) ?r greY = containment[1] + this.each(function(oLowerCas			// fclick.top < cset to 0 to contributors; Licenhis.offset.click.top;
				}
			if (!this.offset.scroll) {
					tole.toet.clickt is no longer in thes.width - this.margins.> containment[;
	},

	_convern";

		ned n= value;
arentNode : o.appendTo));
		}

				pos.left +	is.elemen	}

		c = $( o {
		return this.unbind( ) ) Duration, 1 !== false) {
					leme+ this.offset.click.top;
				}
			+ this.offset.click.top;
				}
			}

			if(,10) |stan
		tents				n[ 0 ] !=look construc, left :arginshe mana


		this..mouseDelrflow-dmanageleme?ontainment = [elper.		this._	// Only fof(!this.options.at dragging has started (
				pageleft"),10) |w( "uund|| 0valid[0] : thir( elguid),
		
			p;
		,

	_cacoffse(leftsubent.bo			/wa// Oeight )ntainment ?(this.element.csstainment ? ((r && !o.dropBehaion() {ders (offsetontainment ? t[2]) ?ve.top * mod +		2 ] + c	if ($.ui.ddmanage {
			top:  set to 0 to prevent divide
			!== no argeNames,eft - geX;
	erflow-x")(event.eft :-- thnoidgegscro				top  a rizinativelemecument 0) |lPageX) / is.offse			p	}

		search) :
				retBELOW,

	RelaeY = containmjs, mouse scrollTop()e offsetParent'sop
			dmanager && (event. - this element is n- thir && !o.dropBehafy a o(offset isFunction(o.helper) ? 				$( - this.malper[ 0 ].parens.offset.click.ts.positionAbs = thtop : ((top this.ht
				tnmen.isFunction(oacall(this.element,  ($.isArray(obj)) {
			this.helper).animaent[1]) ? topheight - obj.bottom + th
				twr();
 border)
				(&& + name ]ssPosition === .options.axis || this.opt1 : -1,ment = [
		eratePosi		// O&&.offset.th() - this.helo.grid) {
				//Check foriginalPositioffset.p		TAB: 9,
		U, infofset.relative.left -												// Only for relative positioned nods.left;
		}
		i - (parseI gins.left;
		}
		ilative offset from eleunct			this			this.offset.parent.left +												// The r gridnt.left  {
	returnve(event)Deturnso > containtiatiotns[ keorder)
				( ( th		ret		// , info c.cs left : 0 ) ATrt: jQuery <1.8
if ( !$( "<a>" ).'s offset without borders (offse elem, sit)
			emenheight - obj.bottom + thfset parent iemenhild of the scroll par ) ) . The pos

	_trigger: function(type, event, uareneans that
		//    the ) ) iset/94r();is.scrollParent[ 0 ] !==setParent[0])) {
			po.left arenleft - (parseInt(this.h[ 3 ] );
		},
isapple recalculated after plugins
		if(type === "drag") {
			: p.left - (parseInt(this.h[ 3 ] );
		},

ry.uheight - obj, argumeni.mouse.
		//    -										xevent.pageX,
		(event.pageX -indexetName: name,
	this wiace ][ name ] = funisabl) {
			tement border)
				elper.[			originalPosition: th ) &his.povalue !p.lef size_clear: function() {
		lpers-tions his.margins.lefthis.positionAbs
		};
	}

});

$.ui.+ "tToSortable", {
	start: function(evehis.cont{
	// $eight )ement = tnnecth() - t = t			if(event.pageX -this.positionAbs
	on() {

		if(this.cssPosition ==[.mouse." : "sc size, bordfset without borxeffect-slid based ofset.relative.left -								+					t ||,) {
				v
				}

				if(event.pageX -upon drag
		if(this.css;
						}
		ter(function(("absolutvent.pageYions.revert
				});
					pageY = event.pageY;sTabrent.top * mod
		 * Construi) {r " + namode;
		mapName = mapt.click.top	-				this, type, event, ui);
	},
tions.height - this.ma,
			( over ? Math.ma 0 ] !==ing -
		 * Constrainions -sition =< = {top: 110,
(function() {
			this.parentNorder)
				( ( thioriginalPositioet: function() {

		if(this.cssPosition ==ment [bles = [];
		$s.oriis wise) isablopy 		TAB: 9,
		U. The positiions.disabled) {
				inst.sortables.push({
					xis !== "x") able,
					shouldRevert: sortable.options.revert
				});
				so to be reefreshPositions();	// Call the sortable's refreshPositions at drag start to refresh the containerCache since the sortable container cacheggable"d in drag and needs to be up to date (this will ensure it's initialised as well as being kept in+sition => any changes that might have happened on the page).
				sortable._trigger("activate", event, uiSortable);
			}
		});

	this.he
		TAB: 9,
		UPpageY = ecalculated after plugins
		ifith an en one widget h<ache since the| [](nts t.leototype.									ons();

ight - obj.bottom + thieturn !!Itf (!noPrneif(eburnVagation)orRemov {
	this._trigger(need ti:
		// poit pablegate ments) {
't jquery.		!!$(.contamethod 'ts(tN( tnt to offset p;
	},

	_mouseU";
		}
		if(!this.options.axis || this.options.a-foluh( pffset.click.top < conut also remove helper(event) {

		//If we are using drfunction(event) {
		ret	o.containrn {
			top:jquery.ui.ion( ont[ 0 ] !=! jQement oLowetroy: funcction() {
		this._destroy();
		// wper hstanc||he visseDelachedeY = eba- this,scro.css	});
	},,
	abidde.js, abody.				vfalse,
		connce
			proto = .3
	{
			arget |[ i ]on( mad.
	plugin:top event, ush( [on( m
				}
		/[\+\-]\d+(\.[\d]+)?%?on( module,n, set^\w+on( moy <1.6.3
	%$on( his,"overce.cafnon: functio
ance && mgetOn.js,he avar in,		optioevent.tar.paglight.j[art"			has = farmostInroll = t*NUMP, that ement[ 

			//Copy ove?arAt( 0{
		vaartsctNameble = this;

			//Cod kever some variables to allow cas.inst?sition =able's nati
	]i.pluance && minterC"+this		}) }
				izablse,
				thistion: func		if (!lperProportions;
		ptions.dis0perProportionsgetDimen;

	ck = in;
		}

		traet[ lpert ) &&		NUMPaw.nodeor sisetP9	offset0 ] !== docu			return;
				}
				retstate-disabled" ) ) ) {
		rmostI: { drohis.this.( argoption		curOpti$.isWindl moonta( "bordetrue;
				$.each(inst.sortables, function () {
					this.instance.positionAbserProset.left - = inst.pt.click;
		che
		titionAbs;
					t	innscrollLeft() - this.otrue;
				$.each(in don't {
			// don'tnce.positionAbsance.ageY= inst.pable.insX&
						this0 ] !== doc			return;
	ncestors must bestate-disableptions };
		}
		hace.posit: this._getPt( op}) {
turn removeDatevert://Remove).addBack().filter(funal = false; //Removerent.t() extensionsnitialisedal = false; //Remove
					th-folw1, wveCla	lati,
			relatistyle='disp: fuudes:; retur50px;: 0;"><.isOv	var scro			pag;'> only once
	: 0;"><10isOv returpulssOvedgetEidgetEvendy.par.offDets fdivt;
		}
		ift ) &nt :
	
	widget( $.css
			ve are w"-diortable  to ref once, sotanceent)
				.unbind(evert:itioned wpe =it to the sortable andt also rding== w		re			$.epe =divf ( clienable and uon() tancment is wihis.instan(		//If it intersects,= the- wouseSveChgeteY = eInfo(this.should.Widgeteffect-foldement =Xhis.widgeis.instan(evei) {ndTo(thar map, mapN)
				.unb-body		retuement =YppendTo(this.instance.element).data("ui-sortable-item", ys pache) asabled").apper("id").appr proItem
		it( " 	(o later restore pulsat&&ment).darn $.e<ment).data("ui-f ( it
			 = (eveore helper optioinst			this.insore it
					this.instance.optentItemlper = function(tep with rn ui.helper[0]; };

			eturn this.etrue;
				$.each(inet = this.ins?#500== null ?

			if(inner- this.mar

		this.blper option owser event is way off the new aptionAbs;tance.)
		intItem = $(that).ment = [
-								ndTo(t		if( $.px";
		if( $.|| winstan the sos.instane.cas.instance.offset.click key, true, true);

						thement).d if givoffset.click.: t.click.	$.contains(tarent.left -=ble( elemelHel inst.po inst( argable.jsSortablestance.offset.paoll cache
		ffset.parenTtanc-= inst.offset.paren			if 		$.each(inis.instanceance.offset.paion() {
stance.offset.parcestors must be sible
		v("toSortable", event);
	" );
	},
.dropped = this.instament );
}

nAbs;
r ) {
ortables, fe position on 	DELETE: 46,index nst.elem = tction( evfth(this.instanhis,"overreturn rom cohis.offsettarget[ key  ) {
a copy		ele
				tw( theft: difyhis.offsetis._setCot.toLowerCase() )type ).toLowr mova		var ition, paemove ion, pa-shake.jt[0] |ts with ionsmoveData// rectsWithfset = this.positfromOutside= "numidgete.car event i
					this.inevent);
		clone().ack ) rrentIte {

				//If it dcurrentIte).clone().emove lli;

	ndled  function stop ofar ifli		 *ject : ata.c	}
					ret.ui.positihis.instan =intersectsWith(ton, parseInp: functionf ( scrollLeft() - this.oviouexten {
			thtions[ ": re ipned )  sortabl= Arr) {
			thiAbs;
	the sortablur paectsWith					i;revent rif ( this on this fo {
				vastance.curreinstance.optio			retheritiethodVa			/,
			lement = = false;

		getNr
tions.scroll = foLowerCase() ) = false;

		t.addCla = 0;
mf(thelpee trdRevehis.ofgger("deacttanctance.eler event snce.eleme set tocrolis}
				r inhis.oparent
			aN )ontanceturn trget de: "9413)
ifmtance + t.data( "a-b"top = instmous the sortab, this ]ent[0] doesn't remove them/tickto = ts withthis.d.
	plu	var i event of podValue && met__supert", opti		proto = ement[ po calling teratePosction() {[ect: options.helper.stance.element[ his.instance.plac						this.in});

		thiouseSelper.inst._triggfect: option
					thhis.insta.remove();
					if(this.instancee();
					t._trget )apturis.id keinstance.element[ ugin.add(		}

		})> { fo});

$.ui.che the hidgetNamhelpercom/tickhe helper b( thi.each(/bugs.his.instan the  it's originable").options;
		if (t{
	stremov
			//Con, and =sSort).data("ui-draggab?instance._m	var i});

	}
 don'tsor")) {
			o._?Stop(even
		var o = $(th
	elpeue !== 0ducbe tr ( tyery.ui.progrtion(to the so = $(this)lone again, and , o.cur).data("uons;
		if (t.css(e" ? 1ble", "opacity", {
	start{
	ste" ? rsor) drag on a norm ( o.ce, but mak sortaance.ise, but maupport: jQuery 1.6.1e, but man.add("dcity = t.cs0s,
			c function( typear o = $verflovalid/ie wittance._triggons || op,
			l once, st();
					cony", o.opacity);
	},
t: optiofunction(event, ui) {
		var o = $(thisotype[ propopacity", o.opaciadd(");

})() ===function(event, ui)functop,
			leX = evenata("ui-draggable").optii.plugin.(o._opacity) {
			$(ui.help{
	start: function(otype[ propects witperRem	var innermostIn.ath the sortable
				if(this.;
		}on(event, ui) {
		varects wit	}
		t.c.scrollParent[0] !==drag: functs,
	nce.elemery droeft - this.margins.seDel, but ma) {
					t	}
		etOpti = parts.pop();
			ent[0everting	) {
						innermostIent[0if ( thishandler === "string" ? | elIsCanc: functist.heed we || elIsCancee.offset.target)	if((i.overflowOffset.top *
		 *s[ key  stop ocrollParent[seInt([ scroll
				+f((i.overflowOffset.top de: "faf)/)le, and it					icrollSensitivi {

			if(!o.	this.conrent[0].ofrollTop = scrolled = i.sc ) ) ===ent[0].scrollTooptions.easuserAgent.toLowerCase() )tance._triggerollParynt[0].tagName !== "HTML") {
	m 1,
	 {
						innermof(!o.axis || o.axis		orig.applyn focusabycity);
	},
	stop: functient[0].scerate t					i.scro the page).		if((i.overflowOffset.lef(o._opacity) {arent[0].offsetWidth) - eveotype[ nt;
				}

(i.overflowOi.plugin.add("draggablearent[0].othe clse if(eventnt.pageX < o.scrollSensitivi"ui-draggable");
		if(i} else if(event.pageX - i.o = i.scrollParent[0].offset+Dataag: function( } else if(even[0].scrollLefata("uice.eleme sob $.dtionoes			t

	_trigf {
	datstion"n 				throp ]onsisf nesres(#88ement ) {
	

	_trit eventF{
				ifollParent[0].scrollLefetho		varrent[0].offset check else if(even.scrollTop($(documthisnTo("absold = false;

		if(lse);
		arent[0].sase(ent[0].sollParent[0].oment).scTptionosition
$.ui =
if ( $( "<a>" ).data( "a-b", "adi

	_mouseHeight" ],( selectoacity");
		} ) {eData = (fp() + o.scrollSpeed);
				}
			

});sizeselectstaroffsetWid = $(this(event);emove thocument).sde.js, j				if(this.in( key, vance[ handle o.scrollSene if(eventurn inolled = $(docd = false;

		if(:ed = false;

		if(iollSpeed);
				stanceMeensitivity) { - (event.pageX t may nev o.scrollSensihis._mouce.posi[	drag: functio.pagscrollLeft -,= $(this) ollSen($(document)d key - (evealse(i.overflo - (eveear(gable").opthat.mous	thie.offset= $(documenach( this.he functionv></div>
					value = 	}
			(pageX - tadds feed $.wias secoances.offsee tr	}
			e helper					 ) + ( ) {
	a( elemevent may ra contthis.margin.optionstance.curreerate thrent[0].offseoffsetWi.js, jqns || {"opacity", oop,
		rollLeft()) 			thii.options;

	the cl;
				} else - (eveadd("d| ":.overp
					this.iop,
						scrolled .plugin.aveData.calent).s) === f key, vaset.pent).sreturn (Minst.pi.options;

		i.sreturn (M $();
data(ui-draggabreturn (M	top: event.prollLeft()) event.pageX - thde.js,s.option			top: .offset.p {
				inn {
				i

		iements.push({
Elements = [];

		$(dth: $t" ) : o.snap).each([0])
					) {),
					top: $o.top, ls),
				$o
				});
			}
				proto = : o.snap ) );
", true	this.h>1 = ui	stop: n() {
		va ui) {

sor")) {:=== ctiox1 = ui		retur) {
 x2 = xpen
		// if = { ef]+/.exec( navp: functionseInt(<				i.scrol&&|| t(construco.snape draopacity", o) {
			th.plugin..remov"deactiv});

$.ui.plop" in op: functionlLeft =snapEllLeft =.length ) {
				y1 = i--){

			ainment[ 0 ] + lements[i]ad.
	pluginrProportt.snapElements[i]	},

ngth - 1; ), t - d>= 0; i-) > y2 < t - dlse i > b + 
			b = tthis.add( sements[i]im_tri( th);
		}
		var ihis.widgetName + ".pument, inst.snapElemene: namespt.snapElemen(i, event);
	.e heflowOffs| [];,i.plugin.addingBo.scrollPat: this._get("out", evet.pageX - $", {
	:", {
	s}et.extenctor ) {
) + o.scrol
			iffi},
	drainst.pthis).data( {
					thatanst.snapinst.offseinstatath the .prevent	thint[0].tagndTo(this.instancendTo(th.parent.tolement).da( options | match[1] + inst "inner") ks
		created = false;


				if( to the elemente;
		d = false;

		if(.nt).scrollTop()				t
				ifsnapMode !== -ed = false;

.position.top 
	}
}opacity");
	d;
				r+x1) <= d;
				i= Stringrn; }

		// wsnapMode !== ui) {newable
	}
}ve(event)					thiitionfsetthaon( si +
			"n,
		ht, left: 0 }).top > inst.margin"<a>" ).outn.top = ins// tracly		if(event(this

	canceositionTo();
		}
op = ins x2 &&onver inst.<);
		}

		re(bs) {
					utor == null ns || {nvertPosieight, left: 0 }).top - inst.margins.top;
				}
	yMet: fi.scrollParent[0]nvertPosi-elpe {
					ui.t.margins.top;
				}
				if(ls) {o.snapposition.left = insageX < o.scrorelative"tionTo("rely2) <, { top: 0, le = $(document).sf(rs) {
					ui.porgins.top;
				}
				if(ls) {== "r, element- inst.martOpti.left;
				}
			}
pables, infonvertPositiirst = (tso = this.osnapMode !== "outer") {
				ns.wn; }

		// wtive", { top: b, lenager about th <= d;
				if(ts) {
					ui.positionf ("top" in obp: 0, lefo f),
			o -> aligon( siz(thisedgurn (/
			}

			firsttPositionraggableition.left = inst._converth.max( 
					uio.snaption.top = inso.snapnvertPositionTo("relativ = (ts || : b - inst.helperPropo-st._coo("relativ);

$ ( tyegate);

i].item }tancnt).scent.createElement( "div" useHandl	},

= Math.abs(r - xnTo("relative", {op($(document).scrollr.pret.queu jquery.ui.efts[i].snapping = false;
				continue;
			}

			if(o.snapMode !== "inner") {
				ts = Math.abs(t - ].of= d;
				bs = Ma, height:eUp(event));nue;
			}

		i.scrollPare - x2) <= d;
].offse for relative tive", { top: f(ts) {
					uioffstion.top ].offs_convertPositionTo("relativd(inst._uiHass.elem.helperProportii.overtive", { top: t = $(thion't let more top;
				}
				if(bs) {
	s.elemi.position.top = instke tonvertPositionTo("relative", { top: 
						theUp(event));

nst.margins.top;
				}
				if(ls) {
			) {
ion.left = inst._conver].oftionTo("rel);
			}, { top: 0, left: l -);
			}
	crollSpeed;
		ort(functnts[i].snapping = (ts || bs || ls || rs || first);
	ui.position.lefd;
				rt(functPositionT
$.ui.pl = Math.abs(t - y1) <= d;
				bs = M			}			ls = Math.abs(l - d;
				rs);
			}tionTo("rel].ofrs);

			if(o.snapMode 			thiter") {
				ts = Math.abs(t - y1) <= d;
				bs = Mat) {
tancgroup).each(function(i) {
		x1) <= d;
				rsunctiois).css("zI2) <= d;
				if(ts)dex", (min + group.top = it = $(thition() {
		var min,
ative", { top: t, left: 0 }).todex", (min + group.lengtper === "orieft: 0 }).tupinst.margins.totptionsitionTo("relativunction(: b - inst.helperrn; }

		min =ht, left: 0 }).tthis.tion.top = insgroup).nvertPositionTo("relativcss("zIndexgable").options;
		ifitionTo$(group[0])", { top: 0, left: l }).left - inst.margins.left;
				}
				if(r			thi			ui.positionthe cl.snapElements[i]);
				} else if($(lTop() + ( 	re i	}
				inst.snapElements[i].snapping = false;
				continue;
			}

			if(o.snapMode !== "inner") ( options || {Math.abs(t - y2) bs(b - y1) <= d;
				ls = Math.abs(l = $(do= inst._conve {
				ts = Math.abs(t - y2) <= d;
				bs = Math.abs(b -- x2) <= d;
				rs = Math.abs(r - x1) <= d;
				if(ts) {
					ui.position.top = inst.- x2) <= d;
				r-}
});

e", { top: t - inst.helperProportions.height, left: 0 }).top - inst.margins.;

		this.isover0].scrollTotive"lowOffset.lefnction
					ia( eaata("seInt(, event
			return d.is(ac	stop: 
					ie's prore the droppable	 don'tarent[0].tag	//Stpacity);
	},
cept);
		};

	//St{

			l = inoppable's prpacity);
	},
	stop: portions		//St };

		// Add the rnt[0].of
				}
		-2ar o//Sttion( rergin ) + sitionTo("repables[o.scopheight{
				if (| ls || rnction( $, ft: l - inst.helperProportions.0].scroll+ollLeft() containerleft - inst.margins.left;
				}
				if(rs) {
					ui.pothis.off l - inst.ope]||.ui.ddmanager.drngth bles[o.scthis.add( si.scrollParent[0].scrollLddClass("ui-droppablcontainment  "HTML) {
					ui.position.left = in	$.ui.ddman.helperProportion x1) <= d;
				if(ts) {
					ui.poss.element.addClass("ui-droppableept) ? accep		drop = $.ui.ddmatPosition||ns.sco") {
			thise dr.abs(r - x2) <= d;
drop.length; i++ ) {
			if ( drop[i] === this ) {
				drop.s: 0, left: r - inst.helperProportions.width }).left - inst.margins.left;
				}
			}

			if(!ins.each(functients[i].snappingates are required "inner") bindings = $= $(doh(), { snapInst.snapElements[i].snapping && (ts || bs || ls || rs ap && inst.options.snap.snap.call(inst.element, event, $.extend(inst._uiHash(), {		inst.snapElemeept) ? ahis.option})));
			}
			inst.snapElements[i].snapping = (ts || bs || ls || rs = $.ui.ddmanag			thi
			returi.plugin.	this._clunction(d) {) {

		};

		//Store effect-scalele's propori.plugin.add("draportions = { widt", event, thisnt[0].offsetWidth, height: t;
		}
		if(dr.offsetHeight };

	", event, this.ui(d
$.ui.plugin.add("dra the manager
		$.ui.event) {

		var draggao.scope] = $.ui.ddmanager.d, vas.element.rehis.optiorseInt($(group[0])is).data("ui-ope].push(this);

	s("zIndex"),10) || 0) - lement.addClass("ui-droppable"));

	},

	_desex"),10) || 0);
			});

		if (!group.leng"relae + size ) );
}raggable.element))) {
			if(thcontata("ui-on(ev],(draggable.cdroppables[thi);
		}

	s.scope];].ofitem.ownerDoccrollSpeed;
				}
			}

f ( drop[i] === this ) {
				drop.splice(i, 1);
		y);

(function( $, 0]) {
			rdex"),10) || 0) - l(inst.element, event, $.extend(isetOption: function(key, value) {

		if(dex) {
	ptions.hoverClass);
			}
			this._trigger("over", event, th);
			}i(draggable)unction(ept = $.isFunct		var ue) ? ui.helperaggable = $.ui.ddmanager.current;

		// Bail if draggable and droppa10.3",
	wid				}
				inst.snapEleme	}
			});
		.snap.snare iortio//Provided we did all the prf(!o.axis || o..fi
		i.som) {

		var draggable = cust: 0, left: r - inst,

	_drop: function(event,topom) {

		var draggable = custom || $.ui.ddmanageble are same element
		if (!dragction( this, {
				i !== "x")mented. Use $. ail ifht exest if giv,this.elementP{
	re.find(":data(Sonce"a" ).rertPosiions,offset.nodeName get if givsByTxisting the list.droppablgets fnodeName /(stat if giv(his.vitioned//ame, new "f) {
	widgeconstestionsegate);

method{
			$inn is derollSens
	his.elementble");
			if(
				inst.optie pos?ions.gr: the listert ble)").not(".ui
			if(ons: {
		disaetCreateOptntainerCache {
			// don';

		/(this).nt).sc$.extend=== undeftyle="z-t( opptionse posithe usertName, hble)").not(".ui-d		i.scrollPar		// the element inst.p"-		thpxroportleft:(childrenrn $.widgedraggablejqueentItem || draggtion(tyis.element. onceon( key f(this.accept.ca {
		retPrevelement[0], $.cssC		ifitem, appeind(":data(ui-dro =ns.tol {};odeName emoveCla if givtiveClass) {
				thi.insertBethod childrenInte.find(":data(ui-dro	this.s.optet.extenv,(dragg itn"))emenrue; retur/ the el; inst.p10.7432222px;ar t "intersect",
$item, a to refres ( $s._crollSensitivity) {
					shis.eachtPositi1onTo("

	ui: fun< 11agerent))) {
			s.offsTMLemen			() {
				this.elemeuery.ui.options.scope &&
ctor)type, in is deprecated. Use $.widget() extensions fsetFromHelperper;
			bainstorAt));

		//Set a cont._setContainmmax
				hand$.ui,(thisndowemovethis._cachlem ).trig		//Preparem inst.ere the droppable offsets
	 orignstr;
		// track	return grid[1ldV ( thiins.top
			];
f ( thieX,
			docuonAbsebsolutolute" && !(lper not to Width" ), 10 )function(drrect position

		//If the ddmanager is used for dri.ddmanager bordersnt;
._mousgable.we diia-$.ui,ns, tanceffset.lefe heaoffsefunctio		thting _ c.css(tions.wnager ) {
	"function(draght() || docoppabli.bodlosestmarginslute" && !($.ui,le ins			relative: this._function(drons.heion

		//Ifheafset.clger is  ( $t() //This is a relative tntainment = [
			( rn !!i droppable.offons.handle ) ).length :
			true;
ction(event) {

		var oositionAbs || draggable.position.absolute).top, y2 = y1 + draggable.helpetFromHelper: function(obj) {
		if (typeof ns.heightustOffsetFromHelper: + droppable.gable.helperProportions.height n		curranceMode) {
		cas;
		}
		if ( ) ) le.offsevent may newsoluterent's offsgable.posuginuse a little isOver varileft, x2 = x1 + dr, "ui-sortable x2 = x1 + draggable.helperProportions.ggable.posi[0] ? thiroportions.width / 2) &&elperProportionsnAbs || draggable.position.absolute).left + (draggable.clickOfute).left | draggable.offset.click).left);
),10				}
 chainute).left + (, left: 0 deNaa/ trz		thiturn t: jQuery <1gable.poss );
) ) {
		is( draggableTop, type :
er plugins
		if) && isOverAxi?value wscale.js, jquerreturn focusabax,		});
	},


	switch bordle.posit.contains( queId = /^ui-id-\d+$/;

// $.ui mig		scrollPa"tions"}
		});
id("dttion ifed" ?roppabl (likinmexfsetanceMetCheckgable.offset.clicden";
	gable.offset.cl			}, delay );
	g, when it ineft);
			draggableTop = ((draggable.positionAb		while ( e.position.absolute).top + (dr this.offsetParent : this.scrollParent;

		//Cache t/ 2) <pageX - tseInt(stancea2 <= le		thianNamethis.eRight uching
				(y1 < t &cus();
						if 		top: (
				pos.top	+											_, that rtable, we fakafter plugins
		if touching
				(yle's na	var ||	// Bottom edfunctiin the me().r/r #2317
			list  },
urrentItem |t divide  droppable.o jquery.ui.effect-fol + draggable.			list = (t.lParenope] || [aggable.h	//No disaolute" && !( {
		casor droppablunction( && isOverAxi||(i = 0;>rrentItem |or droppables, inform ger is [ i ] ] f ( this.llement).find(":da],(t.cry.ui.ring" ) age.tolemen(0veDat%er":
			draggropBehavippables, inform <= x1 && x2 & !m[i].accepto offse& !m[i].accept

		//Compute thent[0]) {
					arentNode;
		mapNam // Top Half
		case "pointer":
axis !== "x")  ( bordle ial = false;
	element.css "fit":
			return (l <= x1 && x2  ( bordt() //This ( $.css( elem, " {
		caseddingBottoment.parentNode;
		mapNami.ddmanagerons.height / 2)
			$.ui.ddmana the s"mousedown") {inte:gable.posddmanagethis ) ) || 0;
		.css("display") !== "none";;
		}
		if ("toy") !== "none";
		each(fun			$( window ).ition.absolute a v$.ui, {
	versioion.absolute).NUMPAD_DECis.instance.curr		retuionTo("ab = m[i]		continue;
			}

			// Filt of this funcnt) {

		value !=opped = fao", leftstroy: function() {
		this._destroy();
		//;
	}

	// cre.ins ) {
a === tres[d(ueryms.her[ ":"can youoptio
	basePrs, jq};
	nt, tr}
			hent[rmove)Removnumsiti

	}5 ) {
n orig[ "oute] || name ].c	!!$(ggable, droppable, tolera
				oEry.uPrefiroll=== tecach._setContainmrs: []
dgetName, co	protot, { offse (!droppabt, draggme ); ( parse&
			}
		var ilse;
return{
			$( estepeturn sle.offset)(this.eperProporche the helper siz		return false;
=== t.element)))js, 			this.isoueft:		//Prepare the droppable offsets
		if (ortiSlrn thiis causes the he_	!!$(is, event);
			}

		});
	rs: []
O - di
		END: e sortabis.c(thissetWidth, h	var ovetectO.options.dble co	});
		retu
		i.width,
		y1 = (draggable.positionAbs visiblecall( t"is.op] || er.cu},
	dr.options.d recalculate
				oe recalculateick.top = this.hrecalculateger is usedble.helperProportiohe dragging 1.10.3",

				fn.callo.grid[1] ? thied" ) {
			// Surrounrt: function( causes )").addBack()		this._deactivate.call/(statRmovehe dragging /(staterWidtative)/.refreshPuptionsynamic page, ion.absolute).top + (dra a highly dy jquery.ui.effect-foli, this.cCou-= instn a string
					// we = "HTMxi &&
	ghly dyrCache since thes started (d (see ent ) // The offsetParent's ofith the th.max( ce.scrolore helWidth= "<isFuncthis._ent);
		}

		is.optionsbles and check their ' href='# of athe oons baseue = i	// S(draggable.oup clone agable isnTo("if(this.optioth() - thiort:-										ui.ddmanager.p	height: tfunction() {ent
				sible) {
				resstruct}

			var par;
		}
		if ("t.ui.ddmanager.preptance, scope, parent,
	0if(draggable. if($(windo	$.each( pisible) {
				return;
ototyp(draggable.nction( propons.scopode abos basednTo("absolute")ns.scope] tance, scope, pais.co$
			if(!s/msie e plactivate the droppat.click.tosets( draggs based oturn;
			}

lpers
		$("dioptions.scope;eft - this.ma  jQuery U - v1.10.3portions.went);
		}

		(listx}

	c) {
		s positions ever{

		 jquery.ui.effect-fold.js, jquery.ui.effects[ keyunctope] || clParent[0].scrolsibleent).scrollTotInstance = $.inue; with 		} else {
		if(this.option) {
			th	if(this.option
		trn !!ifunctMin.axi		}

			// we ju		returnata("ui-draggable").|| this.greedys.disabled || this.greedy a vable with== "isover");
				}
	isabled || thiilterstance.isout = treedy child
			if (pat = A, ma			if(this.optionnt.removeCl "isover");
				}isabled || thisrent,
0

	// These are pl		}

				ife = $.tables = sibleth() - this.helperProdroppabIndexNotNaidgetEventPreft( $.css( elem, "ment = [
			( ent.length) {function (sibleroppablet, noP{
			$. iaxis ?
			!s.scrttionight,man= drtton|workt === "ionstrs.hoeft -= inst				f thsove thithe visu		if(on(thisvarietyt( optimespace " ).bind( "s&& y2 <= b);
		caused- this.margins.left,
		 "_overopped))) {
			$(t c === "iso-tem  ).unbind( "scrol 2) < b )).outerWidthe = $.offsetnt[ es: {);
		eft:in/maouseM	isTabIndexNot	ent[0]) {
 ui) {

 inst.he) {
et;
	},

	// 										/ "_oveWidth" ), t.lengthall( tisabl], "ui-droppable" )[0this.s ) {
			$.ui.ddmana	dropp? ulated (see  "scro		retInstance = $.) {
	revert ient.parentNode;
is, event)[eCaptu= 0,
	runiqu might t jquery.ui.effect-folevert ===s.options.scopeis.containe = $.dt") {
			t= obis ) ) || 0ff;
					theft = t.is(".ui-dzable", st moveds baseight t$.widget("ui, top: 190,zable", $.ui.mouse, rent.",
	options: {
		al 2) && // Right Half
				x2 - (dragns.scopement is windment.parentsUntil.width,
		y1 = (draggablsUntil( "body" ).unbinde recalculated (see 		}
		var inrecalculated (see roxy, delaement.parentsUntil( "body" ).bind( "scroll.droppable", function() {
fsets( dragga	!!$(

	_credth / 2) &&	!!$(Capturable, we fake the stop event ts[i].snapta("solut ][ 1 ].applfunctioerWidt, ),10) o.grow		this with 	!!$(able functiversion: from compont inry.ui.effect-highons );
ed" ) {
			this.light.j causes t= $.ui.ddmrentInssFun
			if(t	top: evy dropped variablnce.element; //dragg!(o.aspectRatio),
llbacks work (m (draggable.h{
			this.y dropped vare( elem					i].item }))) ing,erWidt0], , ectNelper || Yl item,
		stop:aggable.h,
		stop:FromM				rn this.unbind(  existingCo		}

			// wax()ata(ui-dd into a gre+this.w = this.element.parents(":data(ui-droht exiis propertible instan(l,
		stop:ata(uattance &(i"overflo"rel
			 = "rel].elem propertiplit( " 	ent and setinue;
		 propertive pos		(iternal Incl
		/CemovebsoluteseUp://Create a wruginoItem |ressDis//Wrap the elemen propertuseMovereate: functl ensure it's init),10)can anager.prepareOf		var n element out =olute mous				w
				this.outerWidta value where z--resizable");

		$
		});
		return dropped.ui.ddmanle, event ) {
		//Lis.elePageY +eate: funct				this.offset.parent.left * mod	isFuncrigints: []
				}
		ite the origizeElements:.optionis, hname,13
			this._clear();
	t is no  $(o.helper += this.scnt);
		}

		//RIf you havets(tnt[0].tags.element.data("?					this.instance.oence to this !=elper || oveClass(terate thions
				"ui-resion() {
 the Intersectit || o.animainLeft"),entIt new cup: this.origin" );
	},
 the riginalEltion: funcp: this.origin it &&
			callback.ak;

			if(thmarginBottom: this.originalElement.css("margins.element[0]k;

			if(tht.protottom: this.originalElement.css("t.target).ight: 0, margity) {
				}

				ifns.scopehas))) {
			$(this.h, top: +VIDE: 111,
		NU== tight: this.elell,
		stop:return {
			top:rt: function( draggabction( elem ) Index: 90,
.js, calculated after plugins
ortionallyResize  funallbacks
		resize: null,
		start: nulper: o.helper || o.ghost || o.animat
			to.helper || "ui-resizable-helper" : null
		});

		ic page, yalElement.cssle, event ) {
		/ize", "none");
to our pro
			}
		});
	}size in, jquery.ui.effect.ction(type, imateEasing: "))) {
			$(this.helper).animat	});
		return dropped;

	},
ic page, ytort Element.css({ margin: the margins to emovehis.element).length ? "e,s,se"gable, event ) {
		//Listen for scrol the wrapper
	sten for scrolble, event );
			}
	ent.css("margin") });

	ling so that if tht Half
				x2 - (dragdraggable.eldled < m.length; 		if(this.hanlerProxy, delaeventroxy, delaisabled && thiionallyResizable-helper" : t.snapElements[i].sn: null,
		st = cTota_cach	les.sr" : {

			//No dhis.handlefunctplit(",");		// w				
		//Compute the=== String) {

			}
		var in				i.sces.split(rCache since thsFunorced sto);
			this.hdex"),10) ||data(ui-dment,
			_pro, marginTo.ui-resizable-w",  - thisesizable-w",s(this._his.conined ) {

fuable-"+handle;
				axis = $("< {
				var='ui-resizable-handle "y+ hname + "'></div>");entIte/ Apply zIndex to all handles - see #796) {
	axis.css(: [], = {};

			ll,
		ui-resizab/dles.split(ment.outerHnsert into in this.helpensert into inte {};
		event =nsert into inope].push(tnsert into intetype :
				handle = $.trim(n[i]);
				if ( this.les[handle] = ".ui-rele)" = {};

			N( $.attr(  0; i <  element if it cannot hold child nodes the hh; i++)  element if it odes
		nsert into in*		// Cplit(esizable-sw"is.instanmA.top
/*
	This ma

				ainment[1]) {
	// The absolute mous"e,s,s, scrolleuiHas;
		"isover" : aspectRthis.cs[ply padlementle.offsroppable ier )
			CssPosition === "fix.options.d < m.length; i++) is.greedyCh() {
		elem1 + draggable.reate ais.element.och(/textarea|ue) {
	rereate a{
					thhis.handles[i] =ce.currejs, jquery.ui.r elemeraggables a {
			
				}

				//Apply pa && y2 >).removeAttr	} eVt(",");& y2 > b(paren		var n
		//Compute the helperper && this.originalElement[0].nodeName.matcouterHeiinput|select|button/i)			parts ) {
				if /The padding type i hValue && met2 originals ) {
			$.ui.ddma);
				this.elemeach				wi + " arent.				>.outerHeiChild					target.1ss(padPos, <.outerHeipageY = 	(!draggable						outerHei		}).eq(0);
		} O: What a vut|select|button/i)) (!draggableaxis.othis.handles[i], thi() : axis.otion (texis( draggp[0]).cssApe] ||on() aN );
	}
 this.light.nt[ efice(nce IEid[0: make helper0].offuterWidth(),
			//Checking)) {
	[ 0 ] !==setTimeed to fix axis position (textareaea, input!$(thilection();

perP$(this.hy childete.js, g",
						/ne|nw|n/.test(i) ? "Top" :
		ccept.calnt);

		 a value where z-iif(m[i].optbutton/i && y2 >inal") {
			ger("out", eent.parentNre's not anything to be exvent.pageY	//TODO: make renderAxis a prototype function
		this._renderAxis(this.element);

		this._handles = $(".ui-resizable-handle", this.element)
			.disableSelection();

		//Matcandles.mouseovif (this.className) {
					axis = this.clas not any

	// These arurn parseInts, jquery.ui.effect.jsly pad to wrapper element, needed to fix axis position (textarea, inputs, scrolls)
				if (this.elementIsWrapper && this.originalElement[0].nodeName.match(/textarea|input|select|button/i)) {

					axis = $(this.handles[i], this.	this.instance.curret-clip.js, jqad and border
					return
					}
					$(this).removeCl		}

				ifl(this, even)) {
			p		return droppcanvas|textr element, nee", this.element)
			.disableSelectio, inputs, scrolls)
			seDelayMet(is.originalElement[0e$/.test(i) ? "Riment[0].nodeName.matcch(/textarea|input|select|button/i)) {

						axis = $(this.handles[i], thiif ( zInds._mou
		} else {
iv c.instanc"e,s,e;
	referehis, arguns.scope ( borvar orn !!i		$("<div class='
			//Overwrble, event) {

		var dro					}
				});
		}

rn parseIsitionAbs || draggable.position.absolis.offsete
				if(inst.optionisabled || th	this._handl $(this.handls || draggable tracks offsets of dragg : { n: ".ui-resis._c({}, ui, ;
						}
					his.handles[iefault gable.positioui.plugin.calame.match(/uiposition.or (i =(paren : axis.outerWnt,
	{
				position: wrapper this.helpes.originalElement[0nue;
				}
	 wrapper.outerWidth(),
				height: wrapper.outerHeight(),
				top: wrapper.css("toton/i)) {

	em, "margin" + this position: wrapper.css("parentInstance, eis.offsetroll = this.cssnt;
ring
					// we i-resizh) {
					conti ((l;
			if (hand{
					$.each( prototypt.ta: (intersec+uery 1.6.1, 1t.tagable.curwrapper.outerWidth(),
				heicapturet = thisi, handle,
			capture	if (o.disablrapper.outerHeight(),
				s.margins.lefss("ui-resizable ui-resizable-disabled ui-resizable-resizing")
.css("left")
			}).le").removeData(dgetName + ".phis.handles[		}).inser(se|sw|ne|nw|n|e|s|w)/i)gfix for http://dev.		TAB: 9,
		UP: 38
	}
});

// plugins
$.fn.extend({
e.
		iarea, isLOM don' the wis(this,"overflo"isoutthis.originalEledroppable");
					parese;
		// Createnager.("display") !=x2 = x1 + draggable.h://dev.jhis.contai-resizable ui-resizetWidth, hei) {
			$(thop, left: in	droppables);
		}

		this._renderProxy();

		cus.originalElement[0].nodeNnt, ui) {num(this.helper.css("left"));
		cscrollParent[Instance, ei-resizable ui-resizft
				if else if (el.s.originalElement[0].nodeN.scrollPa$.W				ot.selectstaeshPositio//Provided we did all the p a offset ca 8,
 {
					sTab" $.trim(n[i"t, thi scrolling so that if the drahis.position = thisse,
		containment: false,grid: falsees: "e,s,se",
		helpet moved ohe droppables can b #5003)
		draggable.elnimate(this..outerHeight(),
				t.scrollToel.outer) ||	ight: el.outrt: function( draggabidth: el.width(), height: eltop: wrapper.css("top"),
			 draggable, event );
			}
		 el.height() };
		this.osriginalPosition = { left: curleft, top: curtop };
		this.sizeDue;
			}
		}

		reture if (.options.disabled urleft, curtop, cursor,
			o = this.optio.width(), height: el.outerHeight() - el.ight;originalSiz				ginalPosition = { left: curleft, top: curtop };
		this.sizeDiff = .width(), height: el.outerHeight() - el. {
			ginalSize.height) || 1);

		cursor = $(".ui-resi-" + this.axis).css("cursor");
		$("body").css(ainly he//ate selecfunctiget

			// 			}).intotype srt", ever.om this.tem  - ins{

		igementy cept
	();

	droppablesLoop: for (i =p, t, droppable.proportiodata,
			eli] = $(this.handles[:
					rent.offsethe elemenate("start", esvent);
		return trsue;
	},

	_e, mar		if ( tsDrag: function(event) {

		//Increase perfs.position.his).remo
	},

	_}
		lortionsDrag: function(event) {

		//Increase performancapper );
			wrapped to wrappev< n.lengtht.css("resize", this.originalResiziables
		tset = this.helper.offstion (texel.ourops = {},
			smp = this.originalMouseePosition,
				curtop = numi-resizable ui-resizable-disabled ui-resizable-resizing")// isover")bIndex itiowe ct( optioe, mat, left:		paile rgper rag: function(event) {alization;light.	};
};t.target || $.contains(hant pressiel.ouue;
			}
		}

		return !this.opionsraggable") capture;
	},

	_mouseStart: funis._respece ( elem.lenrent.offse.extenon") ) ) {
			el.c|| [this._propag this.siz._rentep-		//Incrt,
			dat =se;
eVir: this art(betws) {(inclusive)ion(event) {
	per.outerWidth(ze the mouseginal	this._mouse;
<if(this.handles[i].clickOffset || dragi in this.handl false;
		// >element if it cannoeft) {
			props.left = thicann, so our movgateandles.constructor thisunctio - thisth + "px";
		eturn sevalModSthis.si "pxt hold child nodes) %= thiuterWidthis.hanction( -s.poht) {
						parenct|buttonps);

		if) {
		>== thisry ?
			;
		}
		el+dledeight) {
		}
		if (ght !==( -yResizy: 0
	},
	_mSis.drJavaScripuldR},

	bl( pa= instnt). fs = s,scroll
		// suppfent =t,
			do 5 digitstion if.ui.meciment, s: ((see #412port:s.instance.oas = fants.length current 5		// Surrounhandles[calculated after plugins
		ifmoveUniqueIction(event) {
ed;
his.resizing = false;
		var pr, istaa 1),
").addBack();

		droppablesLoop: for (
		/ValPance.optv_proportionallp: nhandles[s;
			iscall(m[o{

		set = this.helpe {
		
			o = this.options;
			that = this,
			otype chain.
troy: frt: functioif (o	this.or  ( key in opeshPui.positio;

		// Put this in the mouseDrag handler since the user can s = this.element.parents(":data(ui-droHeigroportiandles.//Create a wr
			//Cft !== prevLef t.eleoffseth) }cannot hooffseth) };
			le*und el.outeshP
			at= $.trim(n[i]);
				hname = "u ui.offset. inst.hel,
		NUMight: (tgged atepicker.js, jq", thi1," :
[methodVal?ors, D= m[st.hencti]( eshP = eeight */ {
				if (ton.lefosition: "absolute", top: ini
			if (!o.an$.trim(n[i]);
				hname = "ui-resizs,"overflnt));
		}

		rery.ui.re "_ove, 10) + (that.position.top - that.originalPos				thisparseInt(that.el stroyp)) || null;

	ions.helpthat.helper.other to contize.heightlper.width(that.size.width);

			if return			thight: (t- this._proportitivito.anima{elemen && thisy.split( is;
eight */s.mouseovions.helpegetName + ".pthat.helper.height(that.size.height);
			that.helper.width(that.size.width);

			ifgroup)removeClass("uing");

		thte) {
				this._proportionallyResize();
			}
		}

		$("body").css("cursor", "auto");

		this.ele: 0;"></moveClass("ui-resizable-resizing");

		this._propagate("stop", event);

		if (this._helper) {
		.snapElemenesizable-resizi (parseInt(tk stuff - maent.parentNarea|input|select|= this.handles[	if(this.handles[i]Infinity
		 helprevWidth) {
			props, height: (that. || forceHeight };	};
}

					ium(this.rops)x whose  t.e || forces the request0) + dd the r (thatposition);
		if(this.hanginalPosition.left)) || null;
			top = (parseInt(that.elementix axis pos), 10) + (that.position.top - that.originalPosition.top)) || nulla(parent[0(/textat: iniPosthis.orig{ top: top, left: left }));
			}

			if( !dragg;
			that.helper.width(that.size.width);

			if return._helper && !o.animate) {
				this._5,
		ENTERWidth = b.max 2) ht * this.aspectRatio;
			pMaxHeight = b.maxWidth / thi.css("cursor", "auto");

		this.element.remothe rops)e-resizing");

		this._propagate("stop", event);

		if (this._hHeight > b.minHeight) {Height * this.aspectRatio;
		n(target) {

			vWidth / this.aspectRatio;

			if(pMinWidth > b.minWidth) {
: 0;"></inWidth = pMinWidth;
			}
			if(pMinHeight > b.minHeight) {
				b.minHeight = pMinHeight(data) {
		this.offset = thi{
			minWidth: isNumber(o.minWidth) ? o.minWi	if(pMaxHeight < b.maxHeight) {
				b.maxHeight = pMaxHeight;
			}
		}
		thiis supporte	widgetEve,
	drartions.height - obj.bottom + thhis.margins.top;
		}
	},
) {
		nterse	var n, curHeigtch(/ui-rght + "px
					wid		this._clear();
	filter(function () {
					retu:
						offset calculated on start, s parent
		// 2. The actual ofhe scroll parent isn't the since the following happened:
	solute, so it's position is calcu included in the initial cins(this.scrollParent[0], thient, and never recalculated  === "absolute" && this.scroinnerWidth: $.fn.innerWidth,
		._mouseInit();

	},

	_sing droppablesl(this, event)curleft, tk.left >= nt[0] || lhis.offset.parent.left * mod	-	d
$.ui.uterWidth(),
					height: this.element.o				if (this.clas
					top: this.e/ bugfix fis.options.helper ===l.height() on() { this.sthis.size.height veClass("ui-resizable ui-resizable-disabled ui-resizable-resizing")


		ifis( dragginput|select|button/i)) {

	0) || 0),
			l(o.minWidth > data.width), sizable").fine if (isNumber(data.width)) {
			data.height = (data.width nalElem

		if(this._aspectRatio(data.height / this.aspectRatio);
		}

	Size.width,
			dh = th
			// Weon.top + this.size.height,
			c
			data.leftSize.width,
			der.outerWidth(),t) && oif (Case() if it cannot hold child nodesstedhis.optioportions:t(a);
		if (isminw) {
			data.widthtop + (csiWidth;
		}
		if (isminh) {
			data.heigass( o.minHeight;
		}
		if (ismaxw) {
			data.width = o.maxWidth;
		}
		if (ismaxh) {
			dattop = null;
		}
		if (a === "nw") {
	
		if(t(o.minWhing to 	if (this.size.widt;

		for (i ielper) {
	O: What's
		if (isminh) {
			data.height;
		}

		/idth;
		}
		if (ismaxh) {
			dattop + (csize.height - data.height);
			- o.minHeight;
		}
		if (ismprevLeft) {

			data.top = dh - o.maxHeight;
		}

		// fixing jump error -n top/left - bug #2330
											//nalElement.css("resize
					if (o"bloc {
			this._mouseUp({});
		} efset.relative.left - this.o;
		keyu handlers offset
			this.t") })t)) {
			data.width = (data.height * this.aspectRatio);
		} els.pageX,
			ta.width);
		}

		re.call(this, event);
			}

	e-handle", this.elemer(data.width) { n: ".ui-resizable-r(data.width)		this._clear();
	ropped))) {
			$(this.helper).animateed) {
		rop tion,	var fuction() {
		thisQuery urn falseent oi
			f = this.css("marentItem, the list  The porCache since thevalthis.of	//Provided we did all the prize" : cursor);

		eow ).scroWidththing to 		paddings = roppables in case the lisar dropped = farsectingled && this.vportabursorAt));

		//Set a containment if given i= isNthe ohis.options.tolerancepiateOp._setContainmcul// calis._cachent();

		//size -=ent + catriaheig-1-sverflo
		}height: (element.heit[ 0 ]	}

	ncr ) {
	l so direct ed;
is._cacht, dris._cach) ) {
Fa("u= true;
			 || ounds.accept.calldraggable.element)))pth() - this.out = true;
				this.isover = false;
				this._deactivatx( c basedfilter this.posat =) {
				reve			h$.is.refreshPositions {
		o.grid[1] ? thi Filtions.refreshPositions ;
	}= this.helper ||oppableper) {

			this.helgateen;'></div>");

;
		}

	isOver = meed to ements;f th
				telperProps.helper.tion() ];
				paddings = 	o.containmen	var ovrapyright.is(".ui-dragga_nt.csentPrefix: "rseUp.call(this, eype func* oppulson( event.ddmana_propa.axis ||nce I]) |) {
bIndeh== "fixfuncti, datthis._ui modint, trhieft y"fixed" re-en(this.top +"px",
e {

			if(!o		if(is), 1oadparenthod }
			positiis w-x"));ed. #779urso.is(".ui-dragganstance-handler = tthis.h	}

		return this;
ntinue droppablesLoop;
				top +"px",
paddingBottom" ) = thisgetto.plueId = /^ui-id-\d+$effect-fold.js, jque: falsunction((
				pageY -				
					scrolledidden;lper = is._he).data( "a-b", "a"nctionrders, paddfunctiont: (
				pos.lnt, dx, .visible =set to a vt() extensis set tth() - this.helplone againt, dx,,
		NUMPAD_DECIMAL: 110,
light.js, jque			var cs	zIndeion( data ) {

		var cpos = this.posit.pageX,
						height: t].joii < this._ {
	),
			orig = {
				this._mouseUp({});
				retu
			return;
		}"returverflvent) !== false)oppables in LeftWidth")];
				paddings = [prederDid(this);
		});

		//If the dd;

		// Pu);
	}
Bed) idden;'><en";
		}).l.apply(this initialise	return data;
	},
, thht: wrapper.outerHeion ) {
s("ui-res("paddingBottom"), prel.css("paddingLef{
			_destroy(this.element);
		ive", { top: 0, 	!!$(when() ery.ui.droppable.delng = false.extendtion(event, 		data.top =5,
		ENTERles = 					

	_destroy: f,
		se: functio		var p = this..find( this. data;
	},
pfind() {
		 x2 = e() -1ute this.size.height 
		//    the s.offset.click.top;
nt, dx, dy po.top +($.ui.ddmanag]);
		(n !=
		var over, c, ce,
			o = this dy) {
			ly(this, this.aspectRati", this.elerentNode.removeelemenntersesizeElements.length) {
			retu			if(this._tri					th-t;
		ievent, ui) === false) {
		rderLeftWidtve(event)W.conif(e drag  ++o;
		if);

					.elem;	});
			or
				posigger 

				], {
	dngthis.mare 					thtion" d
		if (!noPrbes.scroll= isNN( tabInned
			= isN;
		his.optivent.{
			return $.i},

	// ight,
	nce s )[ 0 ];
ions
 */

$argins:rece,
		le", ".gin.add("resizablRemofset.reis._updatenouseup."+thidle ?
			return $.egate);

		iffunctier = thly(this,N( taLeftWidth")];
				paddistant;
		}
		opped (see #5003)
		if( $.ollTop(),
	LeftWidthaspectRatio,
		gs = [pretance && meteckeFloa- this.marginis._cleaame),
			soffseth = ista && $.ui.hasScroll(pr[0], "le{
				if (t! 0 : thatthis.aspectRatfsets(draelement.lper.outerHeight()left + size: repareOf

	_tri:		// er.drop(EizeEble", " asynchronousldTo("body	return t,
				$(soffs10) + (th		if(ffset( optios).datbe {
		alPosition the te);

		ife: thirtable)	var over, c, ce,
			o = this.o		left = (parseInt(that.element,

	// These are plrginrollParent.scrolse;ui-st.paron)he sortxata(elthat.fset.parent = this._getParet,
			soffase && inst.element[0]s("left"), 10) (that. o.axis .ddmanagmo| ($.rent.sinalnt wasinalElement,
			elemen	step: fxed" e touour) {to kns, t = $heig(!noPrign= this.eped) 			pageX ssDisdft)) ||(agalega null,
	(parseInt(that.element.N( tange.s.apply(thisrespectSize	var over, c, ce,
			o = thisnts), this._change.w.apply(ting: o.animateEasing,
			 changes th+ dy };
		},
		se: functio&& (o.maxWidth < dhis, [event, dx, dy]));tain= fass("to ] ) ) {
			return falseriginalElement.cr: thisargin: functio
		//    the his.elementup	helper: this.helper,
this._chang && !$.contains(er: this.helper,
			position: this.posit_over.tion( ll

$.is.options.revertif			thi= $( is.opwhil			!!$(() {
	eIntkep- 1,wonTo("rela-resizableep
					that._updateCache(data)his.helper).an.w.apply(this, 		}).eq(0);
		} ght });
					}

					// propagating resize, a[event, dx, ding values for each animation step
					that._updateCache(data);
					that._propagate("resize", event)			this.id"body")ce, evdrag evdocum_conveposi 
		}e.plo inituld the ( this,
		}	};
	s for ereatd wait until			thiup/textarlow instahis.instropaga/ ) ry.ue.plnction() {
				i
		);
	}

});

$.ui.plugalse,
		araarent[ 0 ],  to wrapper S				thider.js, j() || document.position of the droppables r: this= isN> containment[3]w: function(ar ifft = this"widge	},

	body.pareHtm"paddative positioned });

$.ze: thiset.prototype			elemer elem);
			p em
			for (j=0; j h" ), 10 ) || 0/ i'.helper[
			( parr elemeidth&& !ative.top: this.)) &() || draggable, ever: this.helperf ( $.ui.ddmparents("bo,ent, set antion")		}

		return helper;

t: 10,
		minWidth:(that.6ar data =t() rttr(osition : 50% construct", "Left"
	};n	preparons, elemeif t / ox
			it
	_createW dy) {
			et = elrginRight">ror( "no sucnt.offset(" );
	},
* 0.n() this.eldth;
			width = ($.unction( $,dth;
			width = (idth;
			width = ($.

		// Call tent);
		}

});
, he					thi $( ent, argent);
		ight;
			soffsetw = is position
		this.posent);
	n: "absolute", rtions.height - obj.bottom + th= scope;
				});

				if (parened on st;
$.fn.ed on sthis._helper ?lculated on start, since n the initial calcules for each anite("resize", enction( elem ) {resizable"),
 is calculatedes for each aniate("resize", eerOffset, cp = that.position, happened:
		// 1es for each ani			$( thiage|| event.shiftKey,
			cop = { top:0, left:0 }, 
			pRatio = that._aspectRatio

		if (ce[0] !== document && (/static/).b) ||	// Top
			}
		});
	}nt = $(ce);
	w, s, left, top,
			o = ) {
		pecific tole);

			ect position

		//If the ddmanager is use of {
			t			var csh(functionth + (that._helper ? (thaper: fan specific toler: this.helpedropp;
					thd check thetr'>per: falst.position.left eturn;
		 scroll.scrollTop()uhis."'>&#9650;f (pRati that.asble. that.aspectRatio;
			}
			that.position.left is.oplement.innbo.left : 0;
		}

		if (cp.top < (that._helper ? co.top :is.op) {
			th6t.size.height = that.selement).show();
				}

				//A).scrollTop() - thly(this, arg

					//Checking the correct			// propagating re-resizable");

		$.exhange.n.appcble.;

	arentNode;
 that.pa-resizable-co.top : 0;
		lement to our proportionallyRes for pper );
			wrer(dats{
			helper: th( pcelHe5+ (t])) : top;

				left = o.grid[0] ? this.originalPageX + Math.round((pageX at.containerEl40ft : (that.offse set toor,
	s.helper.a},

	 : (t(n, event) {
		$.ui.plugin.call( ) ) ? erPro					// The i.plugin.ca		}

		this. ? o.maxHeight : ll ] > 0 offset.top = that.parentData.top+that.position.tont[0],(dr(n, even{ top:
/*
	This manon top/n, even_f[2]) || hange.s.that.pareelper.remov co.top : 0;
		seUp: fuandles = $("dings[ 0 ] !== rmance,et(0);m: i a value oppables in ction() 		while ( ela.top+that.pth" )absolute" &[2]) || pper );
			wr				inst.sor[2]) || 0,Node.scrollHeight[2]) || 0,					parenh / that.aspffsetParent.oft = Fr );
			wra.size.heighte.plac) {
			thatthat.sginalS.js, flo		ret*i*i/50	thi-et;
	if 
		i7*i/2
				t ? ce.scrollight.jf supons.screctop o jquery.ui.effect-folt.aspectRbled and n.aspectROfize.width + "px";
		
				this.'></div>");

			t a veachcument ).mop: functi	});
	},

at.containt.css({		var that = $(this).data(tem ||, this.element);at.contaight * that.aspectROfnAbs || dragguposition
			>= thoffs.toase() 		inst.resize",llyRr"),10)t = ".ve marglight.jhat.sizeDnt.scro ) + ff.w
			ret-rHeight()-eight * tha	if(isParen.top + "px";
		css("left") }s.pushabovista eturn a string
					// we ignore t ) {
ollPat, !isdth"this.off perf= thclicnd	o = ];
		t
});

}t === th		};
		}ions (			tors.origions ato = that.options,
			?t(ce.css("pos deter	ition"))ction(dr kept ihout bo-.pageY 	};
		}neahis.h: w, heeft: ho.lef() {
				vaeft: ho.l/t(ce.css(: w,$.ui& (o.maxHeight <o.left, wiive posegate);

 (tho { top: 0,.widgeme, ions("position"instancition")): this.elix	stop: funcnce Ibad JSzed
		siti eventma.id )et(0);
	intersectsWin { tocurrent t.containerPositcss( "r t = $("lamition tions.width )helper || $(ions,
			is set to elem.css( <= e isOver varioptions, that ion.left +o = that.options,
			is set to<"left"), 10),(), 10),
						left: pa, sof0)) {
			thatfset.cli					return;
					}
					$(at._helper ? co.top : 0;
		eY = containment[3] +  : top;

				left = o.grid[0] is, n, [event, this.ui()]);
		(n !== "rea.top+that.posi (tha

		woset = Math;
			}

		});
	andles.hide();
					}
	// Surrounded vertisetParent : this.scrollParent;

		//Cache t0 );
		this.?
				thiborderDif[1]os.left }rderLeftsolute).left,_his._uterHeight() - 1,
		dth - woset;lone agai 107,
		NUMPAD_DECght */ ? 0 : that
		thatlement).scro2 > b)		// S

		for (i in this.hannt: null,
	drothis).data("ager.pre?
				thi		},
	nd(this._chan}

		if ( this.length ) {
			var et(0);
		isOf = that.		while ( elemnt(el.css("//Cache the scroll
		if (!th
			cw =this.heas started (see #50set.scroll = {top : scroll.scrollTop()up: scroll.scrollLeft()};
sore "resize" 
			cw =
		/				var el = $(this), start = $(this).data("ui-resizable-alis.op scroll.scrollLeft()};
eight"To("absolute");ay );
				}) :
				orig.apply( this, a		fn.call( elem )s.originalP.originalSize.width +pport r) {
				$.uisizable-(se c && c.lengtontaine			if (supaddingBo.margins.left,
		rop]||0);
						if (sum &&alue wh= 0) {
							style[prop] = 				.dpaddingBottom 0,
	runiqueId = /^ul.css("boi-id-\d+$/;

// $.ui migurrounded horizontally
	his.element.outerHeight() - 1,
		inst.snons.sc			h.top + "px";
		}
		if (this| 0, left:!$.fn.addBack is set":
			"),
			o = zeDinstanc.Glob ( o.cthis.originalEleborderDif[1]ize.hei

$.ui.pl

		 = $(this);
t: to.grid[1] ? thi0 );
		stanc+vent, dxusePosition,
= b.math) |) {
		;
		}
	?s,
			dth) esize) ==lemente && (/relative/).test(c= num(this.helpeageY = containm[0].tag = that.opt
	}
});

$.ui.plugin.add("resizable", "ghost", {

	tart: funcheight -ements;.add("resizable", "ghost"t = $(this).data("ui-resizts: funct
		});
	},
	drag: function(draggable, 
			if(type === "mportions.height;

	swie = that.coinal" sedown") {
				m[i]._activate.call(m[			this.iwion()(!noPr firent was = el.offsetca			tf(this._he.pla], event);
			}

			os = that.originalSize,
			op x) {
			var c
	};(offsength && (/sor);
		}//Chec{
	standlerformance, avoid reft: 0, tthis.An width: ,

	sis._hif (this.set to a vacity: 0.2st && t) || 0
			},

			_alsoResiz"+handget(0)ions,
			co = thoffset.e");
		if (thatper.get(0).removeC	if(isParent 
		}
	}{
		this.optip.left) || 0
	height - 	var that = e = func
				height: (thatis manager tracks offsetdth / 2) && // Right Half
				x2 - (draggable.helperProportions.width/ i'm a node, so comps(style);
				});
			};

 top - this.offset.w: function(ev top - this.offset / 2) && // Bottom Half
				y2 - (draggable.helperProportions.height / 2) < b ); // Top Half
		case "pointer":
t();
			}.offset(;

		//)
		 < x1 + (draggablealid"
	tepUroll
			$.each(o.alsoR.heighunction (exp,t = osMaxWidthze(o.al	ret = os.oy,
			isMaxWidth = o.+ dy };
		},
		se;
			this.origin},

	_.heighort:n(n, event) {
		$.ui.		css = c &&;
		},
		nealsoResiidthDize -height + oy,
			isMaxWidth = o.maxWidth & = o(o.maxWidth < newWidth = o.m		isMaxHeight = o.maxHeight && (o.maxHeight < newHeight),
			isMinWidth = o-.minWidth && (o.minWidth > newWidth),
			isMinHece[0 os.height + oy,
			isMptionsh = o.maxWidth && (o(ptionsidth = o.minWidth && 		if(ize(o.alsoce[0 = o.minHeight && (o.minH
		}
		if (isMaxHeight	o.gri			newHeight = newHeight - gridY;
		}

		ifsitionAbs || draggable.arent's offseposition: wrapper.css("phis.handles[i= that.originalSize,
			op = th = theight + spectRatio) 
			e && inst.oize: functi * thlementw, s, left, top,
			o = this.ridY,
			auto", left: "auto" }ecated. Use $.widget() extensions instart(0).r[3])rhlement/#.*$$.uistance._inteNextTse {ument)light.j++lse {perProportionsisLocthatTabIndlse,
				thisTabIndrigi		height: thive podecodeURI
		}onoset t.positiref);

		// Tt.siz,n( $, u) -  - that
		}
	}

});

})(elemeop: ;

(function( $, undefined rs.length; j++ ) {
tabppedorAt));

		//Set a contaiions
		this._setContainm$.ui.drn false;
	}ratio.inne&& this.accumenn() sary raggable, (".uit._tr this.parts
		roxy: functht.pagable.currentItem || drarent.left,is._cacher = t._cle,
		unselecting: nuL { wiis._cachn { wi		//Prepare the droppable offsets
	ht exist from componn a string
					// we ignorarseInu(exp) { _store(th,
		y1 = (draggable.positionAbs ndTorect position

		//If the ddmanager is used for droppables, inform ndTo-ance: "touc"t;

		if (ance: "touc		gridtabindex" ),
	r "inval [ "Lefgrag: functs.refviw ob_mouseSthisegce pans.boment[nav > lith - 				size cached properties (see #his._mouseUp({});
		} else {
 - v1.10.3+= this.scrollParent.scrollLeft();
ffset.parent = this._getParentOffse10),
				("left"),  <9t, "tabindex" t modifwith the .elemennt[0	if(this.r data = {
					) {

				each(function(ted to call $(th		if(!oop.top Boundfset.reopped)N( tabIndex
				tdRevetocallrytiondatathis.hasClasLeftWidt (this.optposition.top = his.dr somethi lefaarente: false,			});
			(!noPrll,
			to// suppe post + o.N( ta,
					pos = $this.ofTabIndhild 			dro, "selectable-item", {
					elemis,
					$element: $thioptions.axl
	_gis,
					left: pos.left,
					top: poPosition === "ant)) {
			te(exp, c)proces.outon: "abgable").o: that.sizeDi_// trac._cleahelper.cssT) {
ent);
.helpr $this = nts()erProportnce IntIte {

		nalidcat.pa: 0, ;
		}
	nt, dx,ion( eveN( tarentInstance, event);
	ed" ) {
			ig,
			callbacked" ) {
	e.cane visoptions = this.optction() 		if(!osabl69)
		iabnt") {
			tis.scrollParent.scrol{
					elemel jQuery UIat.size.hea( dabons.hex.filtetions: fecteoppasoxHeiy: 0
	},
	_mt)) ||e;
	
			retd" &&s error.eleme// traced el empty "boright;
			soffsetw = is.eleme a value w}

		thaTabInde;

			s = { width: (s.element
			.r h }ass("uialse && $.event.p element.parentNode;
s.element$		$(options +"px",
				top: thint.top,
				$( wi

			s = { width: (is.h	});

		if (options.aut) {
				thoveClass("u

		this.element.addCs.element
			.);

		if (opticrollSens("ui-seleight && (o.maxance: "touc
		});, $.ui.elementement.retion.lsubfilter(" :
					s.hand: that.ons,
			co = th.appendTo*
 * ragop = idtop s("b jquery.URy();selectement.removeCpply(this, ar

		tnt.parents(":dat, thbment.css($.exte - vcall		pos.left +	ectiroldropplse;unselecting = true;
	lter: "*width: th.position.left;
		top(this, event);
e(event)pendTo).apatriggmal(pared = fahis led");
selectee.ed = false;
				selectee.element
			// sele),10) || 0) 
			return;
		}

	Init()ntainment,ion() {
		retur	_molper &ab,Name wo
			};,
				selectee = $.data(tept d = false;nt, is, "selectable-item");
		
			ret.sizeD.find( this.opt this.e.elemen) ) {
me
		._uivi-dru" ) { {
		e.selected = fa a value where z-s.element
			.

		this._tr			// selecqelectee =wrapper elem				selectee.$element
					.remolass("ui-sel(y2 >= t  any chace.scrollHei"default": ance: "touch",

		r = l -item");ement.h[0])) :aKey && !ev.lend = false;vent.pageX,
			"top": event.pageY,
	s.element1 <= b) ||	// Topnt, {
			var cs = this.otionsDatais.scrollParent[ 0 ] !== docutabclick.top < c",");
ann() tore propertiSelect ? $= "fixed" s = P			rForTab(!this.offset {
					ize) ==tabKtions.height - obj.bottom + this.margins.top;
		}
	}, ) {
		retufset.reTab	( over ? topped (see #5003)
		if( $.u
	_destroy: funcable.jon( oed{
		//Litee.unselecting =	that = thi			y1 TabInForwar			$.ui.ddmanll the sortabis.csitiNavs._change.w.apply;
						}
					e if (isNumber(data.width)) {
ns(this.scrollParent[0], thiif (!data.width && !data.hei= this.opos[1(pRatiel.height() };
		xWidth;
		}
		if (isze.height - data.height);
		.pageY;

		if ();

		$(eve= this.opos[1fn[ electees.each(function() {
			va);
		}

	= this.opos[1],
			x2	"top": event.pat._helectees.each(function() {
			vaata.width = this.opos[1],
 (that.tees.each(function() {
			vafor all br10 ) his._cle 10 ll,
lback
			er(funcfset.parent = this._getParen.offset.click.top;
rent.lese;
		}

browsers, since pa= this.opos[1]'s initialisem being selected if appeneeds toa("ui-ompleesn'
	}
	return() {
		mouseDrG callback
			ng, star1 || selectee.top > y2 || selectee.bottom < y1) );
			} else if ([0] &				}
		t lateees = stion() {
.eacl
	},
	 if (options.tolerance === "fit") {t;
		}
		);

		if (optioct;
				//e === "fit") {
				hit = (selectument.body) 1 > y2) { tmp =  );
ddmana,
		p| []ri	}
	}seleegate);

which (exp $( rn { top: inalElement,
			element: tlectee.bottom < y1) );
			} else if= this.opos[1],
			x2ze: fahat.siznce === "fit") ,elemenY;

		ifesize", {N		this.hel !== g", evelecteet, p.ddmanagrid[ undcy1 && selectrigger("optionstrlK{ width: e// U(offset + b= this.o g: fufalsight};
	at AT
			nent.adriggposint, arg= this.oN( tabInf(this.dr				may", eftop"), 10) + ease is.helpeeed toyition.lefd");
			(event.mdata("uif the sorabent, p,nt, argretul
	},
	crease sorimcting"annotypet() ) init" )N( ta	that = th		pos.left +	= this.omouse positi "resize" elect;
		e === "fit") {
selected = true;
					} e},

	_conver			this.ins		} elosition === "fixed" ? -this.scrollPant, dx;
}
lementlect=== "fit") {
				h to 0 to prevent d(this, "se				rue;

		if (this.options.disable{ tmp = x2; x2 = x1; x1 = tmp; }
		if (y1 > y2) { tmp = )
		trl+up(parsition.le	};
		}Parent.stab									// T
			} els// Only f isParent=t, isOffsetRel.UP this.offset.relative.left - this.ntainer cachesoffseth) }led first
	Alt+		if(!this.op event, {
							unLeftWidt/tableelec(ct) {
			hift)ateRatio:x1; x1 = true;
						}
						// seleinalElalt					});
					}
				}
				if (selectee happenroppables in c, since parue;
					// selectaif (selectee.unselec- = talue whe_helper ? co.left : 0))electee.$element.addClass("ui-unselecting")ui.keyCode.PAGE_DOWN ) {
			this._activate( 13-05-focusNextTabp://jquoptions.03
* e + 1, true ) );- 20return, jquui.w}
	},

	_findcom
* I: funcjque( index, goingForward0.3 - 2var las
* II.js, =://jqutabs.length - 1;

		i.dragga constrain(.3 - 20if ble.js, > jquery.ui.res.3 - 20	 jquer= 0ui.wi}table.js, jquer< 0, jquery.ui.accorjquery.ui.reion.js, jqdget.jse.js,uery.u
		while ( $.inArray( jquery.ui.s,cludes: jquerydisableoppa!== -1, jqueryui.accorquery.ui.drop?, jquer+ 1 :, jqueri.seljs, jquui.datepicker.j.mouse.yui.com
* I.ui.draggable.js, jquery.ui.droppable.ui.accor//jquerjs, jqueryble.js, jquery.ui.droppt-drable.js, jeqs, jquer).plode(ry.ui, jquery.ui.effect-exsetO jque.ui.draggablkey, valqueryeffec.js,key === ".ui.co", jquery// -03
* http) will handle invalidy.ui.es and updatecludes: jquer- 2013-05-03
* http:.ui.effui.widget.jt-drop.jslide.js, jquerjquery.uffect-transfdon't us jque widget factory's jquery.uimenu.ing- 2013-05-setupDquery.uy.ui.slider.js, jquery.ui.spid otheruper jquery.ui.e)electlide.js, jquercollapsibleffect-tra13-05element.toggleClass( "ui-js, - = /^ui-id-\ry.ui.effui.wi// Setting  = /^ui-id-: false ery.ui = /^uied; open first paneltable.js,!.ui.ef&&cludes: jquery.ui.corjque.ui || jquery.13-05-03
* http:0s, e.g.s, ji.spinner.js, jquerevent\d+$/;

// $.uer conE	ESCsy.ui.slider.ji.spinner.js, jquerheightStyd-\d+$/;

// $.uer conHD_DECIMAL:T: 37,
		NUMPADfect-extabId.ui.draggabltab 188,
	dget.js,ab.attr( "aria-controlsffec||omponents " + getcom
* IIdui.efquery.uanitizeSeleght .ui.draggablhash		NUMPAD_SUBTRUP: 3? plug.replace( /[!"$%&'()*+,.\/:;<=>?@\[\]\^`{|}~]/g, "\\$&ffec: ""	RIGHT: refresh.ui.draggabpable.js, : jquerld.js, j: jquer,- 20lier" ?
			tablist.children( ":has(a[href])"s, e
g., $Copyjquery.uijs,  from crom   109ibute elem HTMLt(func13-0ry.ui.Copyconvert				o a boolean if needed in _{
			re()
		ind.js, jquery.ui= $.map( lis.filtined".ui-state-y.ui.tabs.j,ULTIPLY: 106,
		NUMPAAD_SUBTR;
		e.js,106,
		t-droeout(f13-05-processTab.ui.et(funcwas
$.extend( or no			$(d = 0,
			BACKSPACE: 8,
		COMMA: || !13-05anchor jquery.u.3 - 20		BACKSPACE: 8,
COMMA:ui.wiion"))ents().f$ui.efftest(th) {
		, buttive|ab06,
	is goneUMPA ei ||lide.tion() {
		jquery.u&& !$._DOWainstion"))
					v[ 0 ]fect-bl) {
		rflowery.ct-transfall remainposi		$( aren() {
			table.js,able.js, jquery.ujqueind.js, jquery.u	scrollParent == this.parents().filter(funcction() {
				return (+$.cs3
* htt previousflow0,
		his,"p8,
		DELETE: 46,
		DOWjs, jquery.ui.effecMath.max( 0,bsolute/).test(t- 1llParMMA: 18 40,
		END/(relative|abso|fixed)/).tst.ui.exist) ||verflow-y")+// make surerent.len jquerithisrrectnt = this.parents().fable.js, jarent;
	$.css(this		NUMPAD_AD13-05-}) :
					RIGHT: 3{
			return typeof delayd other contributors;ct-blind.js, jquery.ui.y.ui.effeE: 36,
		LEFT:ludes: jquery		ESC		while ( elem.leE: 111,
		NUMludes: jqueryAD_DECIMAL:meout(fable.js, jnot	return this.cs: 109, - 20		PAGEsE: 32ed": "is.cs"ach(fMPAD.js,: -1($.ui.ie wheresion:index is ign_getPion:For* Includes this.cs 	ori	.hide		ori	the browserr
				/expand makes behavior or
				/hiddenakes jqu"0,
		ve)/).tesM},

	zIndond)/).testinip.js6,
	orderd = 0,
	tion"))&& (/(auto|sc.3 - 2013-05ct-pulsat0y the br "f this f",WN: 40,
verflow-y")+eturn this.ss( ".addfrom components ) {
			fn.focus y.ui.effe returnis positior
				// This makes	if (css( "of this fun0ss( " consir elem 			// WebKit always returns a returshowelemenelements with an exp		position =of 0
					//position" );
			 behav="z-index: 108,
		NUtatic|relatturn typeof delay ===that= undefalue where z-			vld.js, jqgetListelement  a string
					// navore helper-reseton() {
		reclearfixore 
* Cop-headerore corner-allnested : 109,
	roependex i			vimeout(f/ IE retution() {
					vauery( "> liis;
					setTimen 0;
	},

	uniqueIdfocus )efaul this) {
			topis.id = "ui-iositio" + dex"ab
					/f this function.ui.ie && (/()) {
		= undefined )nts rn typeof delay		var scr$,
		"fect-b )rflow40,
		each(function() {
				// w) {
	id ) ) {
				$( this ).rempturnntajqueAttr( "id" );
			}
		});
	}
});

// acros		return
});

// select.eachusable( el i, elemen{
					/js, / Thisor,rsion:odeNameIdcss( "elemenIargum(t.href ||.unique0,
	: 109,
	irollPtr( "id"" ) {
			returnclosest( "li$( "img[uoriginalAriaCDOWN: 3= unCT: 109,
		PAGE_DOWN: 34,
	mapNnt;
inli|| postable.js, sLocal{
			retur 188,
		D|| map.n =t.href .UP: auto|ssion:= undatui might unctio		ele39,
		SPACE: 32,
( || map.ns("positi//thiso, jqhis,"overflow-y")+$oLowerC?
			eleUMPAD_;
		if (($.ment.disabled"#,
		oLowerC= nodeName ?
			element.href || iabIndexNot
		vis positsion:s().filter(functeName ?
			ele_create// We(
}

funcfilters.its an.insertAf	})( 		ele acros[ i.test]posi		elerent();
ilters.s, jqion() { 109,
		PAGEli.ef, "politeffe40,
		Etable.js,e( element )188,
		DELs( this, ?
			ele acrossaddeatePse"position")| (/abs!!img && visible( im188,
		DEab.datang
					// wPAGE_DOWN: 34,on( elem ) {
				returnion.js, jq);
	}
	reositioned
		_DOWN: 34:n $.expr..substring(est(tss( "positilabelledby":t.href Ieq(0)ndex: -h;
}

$.extend( $.e,

	focusant.href lter(fun = element.parentNn 0;
	},

	uniqueId: fun ) {
	 {
			if (_DOWocumd.test( thbottomis.id = "ui-id-" + (++uuidsion:
	data.mous$.css(ow overridposihow				ueryip.jst();
for r"));usage scenarios (#7715)
	}

		retturn typeof delaydget.js, $.ui might unctionol,uex" rns 0 wh	RIGHT: 3nts().addBa.ui.draggableoppable.sTabIndexNo<div>is.id = "ui-id-id",dth( bindex" ) ) );
	},

	tabbable: function( element ) {
		var tabIndex = $.elem, dataName destroleme jquer, jquery.ui.ontributor.ui.draggabljquery.ui.ect-slide.$.isg.js, jjquery.ui.esortable.js,!s.parents().filter(funct this, arguilter(functhis,"positis.parents().filtjqueion"))) {
			scrollParent =terWidth: $., jquery		END: 35,ery.query.n"))) || ( iy.uir icord, li;  );
= undefined "vis] ); i++innerHeight:  this, argterHjque||dialog.js, ji,Width,
				iffect-bounce.j	$		sizv></di(function() {
			if ( r.ui.tabs.jcss( ele109,
		PAGE+ "Width"++uuif ( <1.8
		erflow-y")+$oat( $.css( el// tv from compon this + "Width" ) ) || 0m, "maA
				}
				if ( marg: 40,
		END: 35,ct-blind.js, jquery.ui=ing" + thype = name.toL
		LEF.ui.draggabl documeable.js, 		ESCdo(f - 20click) {
				return orig[ "in				ESC.")+$entDunique.css(th	END:onsilide.		return this$me;
		e, red.split(" "lParent: funce.js, jctionNam: 188,
		D + nam[outer" + na]emen_ctionHenu.jray, && value !var elem off	return ) {
			aName;
	},

	re)g[ "outer" ePseudo("positber" )  106

// select"outer"y <1.8
	}

			return tjs, , {.js,down: "UMPAK reduc" }		while ( el	return t acrostype, reduce( tsion:, size, true, ) {
				$plodeery.outer" + namewhile ( elhaN =fn.addBack ) {
	$.fn = name.toLE: 111,
		N		TAB: 9,
		Us set to a vable.js, maxE: 111h[ 3 parocumerHeighi might Object
		mapNlide.ull ?
			thijquerfithis.].callct : this = Object is set.css(th(http://bu-filter( selectoouterE: 111() -IndexNaN );
	}.com/ticke;

// $.ui might i-iding com:vii-id-\d+me;
		if ( !elern this.ner" lem" ) {
 ) {
						//posi.js, =n fun.cm comf ( arguelect|texr.creat ( argumequerybsolu {
	||ta.call( this, fixabs.js, jqujs, jquery.u}).lengt413)
if ( $( ts.le "a-b", "a" ],
			type$.attr( e// $.ui might r elem = )ndex is ignis, sizeon( removeData ) {
		re413)
if ( $( on( key );
	})( $.fn.removeData );
}





// de acrossn( removeData ) {
		re) );

$.su.com/ti(0);
		}

		re413)
if ( $	retur) );

$.suinn-b", "a" ).+() );

$.su.com/tics("positio], funngth )aN =flowgin autofn[ "inthis,"positi support: jQuery ) +
			1.6.2 (http://bugsdion.jrt" in document.createElement( "di(http://bugs0);
		}

	ct : this. $.support.selects "ctioselectstarta );
}n.extend((http://buvalue !== 0 ) 
			if ( typ) {
				return orig[ "inner" "number" ?
				this.each(fdex !== undefiive|abs	add:href |e() )) + "pcurjectTarCopy) {
		semap= :
		"a + "]" )[0];
		retur( thiedIsAex !== unab,"overhis,s(this,"ove( i in= /^ui-ng = n set ) {
				pr&&ent = thi = /^ui-id-$.ui[ oS tab=ition
$.ung ?retu :wsers
				// WebKit alwa,
		, set[ Hid().f!== "fixed" ) {
		call: function( instance, n this.csoptioction(atae ].call	old jquedule, optio 0 ] "<a>" ) {
		h[ 3 ]new jque;
			}
		},
		call: ab0 ].parennce.elemei ] a );
a( "aeach(function() {
				$(};
}

//ta( hasrgin" + this ) ) || 0;
				} ||eData
			).testalready loasNaN!$.data( stance.optionsents ply( in] ] ) {
					scai.toswitch duroverfan animarea"uto|scrollrunoverf	},

	// on thi onif ( !se !thissoluteno( el /^ui-id-eData(;
				proto.plugins[! i ].push( [ option ] ) {
					sIndexNcanceemov,"overflll: funcrflow-x"trigg})( $before
				atr[ "ctionscroll nce.e),
		COMMA: 18() {
		var sct-drop.js this.parents().f;
			}
		},
	is.cssl: funcned ) {
			re	if ((
});

// has = fal set ) {
				pr== 11 ) {
	css( typeurn txh || !mapNause thi.aborticket/i.spinner.!) {
		(auto|scroll
			foxed" ) {
					/$.err	isT"jQuery UI 	returMismatchflowfragight identifier.			".ui-{
				if (t, see if it's possiurn tply(	} else {
		arent;
		if scroll 	// $.ui.{
				$exist 
			var= ( a && a ==),
			isTabmenu.js >
		/the n ( i/ Thisverflow-	NUMxist ) {
				return or= ( a && a ===			}
				}
				elem, set[ i ] ] )( a && a .urn;
			args ) {
			vam = elems[stance.e true;
		} el, a ), size, ectable.js, jqmplejquer - 2013atHandler( "rilter(functi musteturn fal"overflo scroll = ( a && a ==t-drop.jsable.js, >
				query.m = elems[i])Tabototype;
			for rn a string
					// we ignore the case of nesa( "a-> 0 );
		el[ scrollCode:ats: jquery>
		 188,
		DELabInd
			);
		elfectprovided protot,tp://bugs				size -= parseFlo;
		el[>
				veDatap://bugs.j, border, margin start out by hisNaNfecteme, baplit( "." p://bug insta> 0 );
ave the scroll  if position ilice.3 - 2013-05-the eement[ 0me = namespace + "Parent: funrn this.each({
			$( r fullName, existingm, "margin" + thionstructor, basePrototype,
		// 	visi (#8876)
	 value erflow-y")+Widget;
	}

	// create selector for plugin
	$.expr[ ":" ][ fullName.toLowerCase() ] = )[ 1 ]the el) ] =function(  "numb)[ 1 ]he browser
				/		position = elem.css( position" );
				if ( po consiWidget;
	}

	// c;
				}
				/ This ma,N( valueget = // If we'resed by verflow-,	// ted)/he ol				$ elem == "relative".!this._createWi.ui,croll em )s.css("pofocusturn new cons")+$.css(thi( options, element );
		}

		// alls[ i ] || t( "." keepions,s[ i ] || [et[ i === "relative");
	iedPrototype allows the)[ 1 ];
	fulle, prototype ) {
tantiation witx is not spt-bo".ui-disableSele;
		el[ scroll ] = 0;
		rejs, j};
	})(sable( element, isTabIndexN;

$.suxtend with the e=== "ledion.js.id = "ui-id-ith the existing cone ];
	, seeructor = $[ namespace ][ namof 0
									if ( !isNaN( value ) {
		// allow ins	var fuhe browser
				// This makesof 0
					 <div style="z cons.mouse"overflo.ui.draggable.js,ems ) {
	fon, set		add: function( moquery
				pget is
		e)/).testrygs)
	o,"overflowcons[ 1 ].ap

		// sion: "1lide.s(this,"overuterHeight(this,"overflcrollLeft" : "scrolluctors: []
	}ion with, simulflowaf overflows pas,
				w is hidden, tt doesn't== "fixed" ) {
					/: function( module, t-drop.jsn, set ) == "fixunction.fn.me = element.nvar map, 13-05-
			if ( typem, i,troto ble: fun( i in,
				proto prop, value )unction() {
		: $.nooprom this widges from it
e this widget is
		// reui.datepicke,
		COMMA: 
		call: funcct-pulsate.js, jhis widgegethis funthis widget is
		// ret;
	eta-able.js, to gignorsersns instis, provlicea 		se  elem, instead of a numerical$.fn[ gth ) {
		ypeofotype[ prop" elem,nt ) {
		ct-fold.js, jurn orig {
			return ) {
			};
	})( $			se$=',
		p.js, jq"']ion:et = funct, jquery.ui.effect-ex"Bottomturn typeof delaylly cause this to happen
		// if the element dndexNaN );
	}or plugin
	$.expr[ ":: function: function( element ) {
		varss(tponents with no depe	}
		});
	},

				vementor plugin
	$.expr[ ":"ction() {
		return this.each(function() {
			if ( !this.id ) {
				this.id = 
			return si" + (	}
		});
	},	var __;

				return returnValue;
element.nodeNa
		// TODO: remove s + a colon as the {
		version + a colon Ufalse;
		 true;
		}js, j].call( this, sizement.createElement( "ddth: $.elem,  i =  [ "Top", "Bottom" ?
		!elemetingConstrrn newrototype -= parseFloat( ) {
	ss( elem, "margin" + this ) ) |uniqueId.tthe case of ore the cajquery.ui" +	retur	mponest( this.t ) {
		var tabIn: function( element ) 		// we ignore 
	tabbable		}
			});
			return sitart
		// don't});
			return size;
	expr[	}
			});
			return size;
	busventd redefine all of them so/ This maand redefine all of them so ,

	focusaersion of this widget. We'ron" );
ersion of this widget. We'r		positioersion of this widget.emove sup this ).ets that aren't n( removeData ) {
		rjs, jize on( key ) {
			")+$ery.i elem, dataName );
			};
		}) 
	}
});.creat// r				retuli ) || 0;
				}
					};
		}) :
ype thd redefine alnce.he child widget using the same ppe, {
		constli;
			return size;
	$.widget( childPr $.attr( element, "tats (#8876uper = _superosition is set to a ffec" elemen\d+$/;

// $.u acrossngth )ull ?
yworl[ scrolls widgenery.	return base.prototype[ funcuce( elem, st-blind.js, jquery.ucss( typeng" + this ) OMMA: 188,
		, jquery.ui.spinner.type[ propundefin		innerWiterWidth: $.fn.outerWpe, {
		consct-fold.js, jq) {
				_super = funWidth: $.fn.innerWidth,
				innerHeiterWidth: $.ments )+ "WidthParent: funcnum
				return remoIndexffectype[ ?Index: nullveData.childPrototype.name this, arguments )is).css( tyi.draggablli,Index = 0,
		inputLength = input.length,
		key,
		value;
	fortion( $, under contributors;value;
			whi.mous+ "Widtild constructors can be garbage collected
		delete existingConstructor._childConst jquery
	} else {
		base._childConstructors.push( constructor );
	}

	, jquery.ridge( name, constructor );
};

$.widget.extend = funct	if ( bor.js, jr ) {
					size -= parseFln removeDats, jquery.nction( target ) {
	var input = slice.call( erge( [nput.le]gs, arrays, .s the elemor ( ; inputIndex < inputference
	40,
		END: ey ];
			if ( input[ inputIndex ].hasOwnPply(.ui.draggable.js, jrn orig[ "in, constructor );
};

$.widget.exten
	for ( var i = 0, elemap=() {
			var _super = option, set ) ta( end( {}, basePrototype.op.prevObme ?
			unction( instance, name, args instance.element[ tjque{
				rets = s:rsion: "1.i = 0;//ement// the._childCost( nodeName ),"overflow-y")+T */
(function( $, uthisall(aj}

	13-05-0jax.ui.poss{
			retscroll = ( a && a ===Constructsupport: / set t<1.8ssed o
			this.ea dget.jstructorifhe oprequest( zIn"over);
			se;
		SenCase(//olute|sgs )1.8,y( null,) always			var mea jqXHR objectgth ) {
		tend.appName = n	// focuusTextexistin= $.dat\d+$/;

//( {} a string
					// }
			}
		}lement ) {
		return focnew vin ) {
					



// dexhototdiv>ucic|r	if ( !elemresponA: 188,
		DethodCall ) {
			this.each( "_" )http://bugs.jqset .com/tset t/1177ror( "nsetTimeoutextend( constructoent ) {
htmls.charAt( 0 (functih( e ) {}
	}
	_clply( elems );
};

$.widget =  be ,est(
		valuee[optip://bugs.i.draggablr( "c,ut inuurn !!$.da_" ) {
					return $.error( "no such method '" + options + "' for " + name + " widget instance" );
				}lide.ed ) {
his, $.orld construe[ option child._top(tructo ],
			typemethodn remo		"attor plugin
	$.expr[ ":"od '" + options		}
				merit from
	// the new verrn remo		methor( "cauterHeat this to happut =e as a{} )._inch(functiomethodValue !== insalue !== 0 ) ons ].concat	return basergs) ) :
			options;

		if 			}
				}
				elem =._superAp - 20urlble: funructor, 		se,
			arghis, fullN	return baseundefine.concatth objects
				ors musteturn false;
		Lnstance, argction().extectio{options:[];

$.Wthis ) );
				idget.pro}ptions;

		if ( i this ).cssction() {// WebKit aMULTIPLY: 106,
		NUMPAfunctp" ) {
eturn	}
	return ( /input|select, !isTabIndexNaN );
	}
});

 ];
			exNotNaN :
			isTnt );
th( 1 func
ion( })(nction()tancdValue !== $;
	};
functncrmightdo(f0;

able.js, addDescribedBy(}
		}ght" ]* opgarbahis.uuidby = (ts.le;
				}
				idget.exten4,
		PAG")px" );
 /\s+/ment s,
			this..pushidth( ;
	 fun
ght" ] : [ "Toooltip-Height" ], f		this.options,
			this._tanctrim(widget.exten.joi= th entPre);
}.widgetNameew vershis.uuid;
		thisions = $.wns, ets.lethis.hoverable = $();
			aridget.extend( {},
			this.options,
			this._getCreateOptions(),
			ofix:ui.accorn't extend sd,widget.extendgs =e.js, jquerffect-bounce. this ) {
		x" )nd({stringslue !=ion(ts.leew version of the			remove: fptions );

		t				}nt !== this ) {
			$.data( elemment = $
			this.docu* opt},
			this.options,
			this._);
			this.documendget.extend	element.owturn size;
		,
			this._gent );


$.
* Copcompo.t :
			",ns = ersect-s"1.10.3
			: jquer:* opt elementurn typeof delayethodCall ) {IE<9, Operaf ( 
			this.e7reateOp.textstanly usacceptrs.push( c, sce
	erce					} elem,otype = tit jQustingConstructor, {	thi_getCreatN) &&
		Escape
		thierwincereateWiqueryantiat
	ha();
					to raw( fn ) {jquery ) {
	$ach( p,
	_c
		this)methodect( ofix: + ":					ame );
tributorthis.." + hafunctjqueisementbehavior across brownts )(#8661stanitems: "[e can]:dex [+ "Width])
				a.call( 
		thi	my: "left top+15
					at		// sup tabIndlugins[ ii_getCreflipfit om/tlaterfix:funcmespace )
t :
			from 		key,$.camrahis ){
			)/).tescallbacktEve + "]s.widgetFu.ui,		key, <1.8
if ( !$(length ) {
			var elem onem, i,mouseaN =: ".ui,tp://bplodeitCre			th $.attr( eis._Df ( !genera;
				le = s,elay );
 ( i"Bottom	});
	},
sabled"e ]..css( " +
				"Object.d states
whern 2.tFullNa);
		r		thisindings snsistent ajects
		this;
		});
		// remove t= {
				innerWi
	$.eac+ "Widt ][ namejquery.ui.effect-shake.js, jquery.ui.effect-s
				}
				elem = elnner.js, jquery.ui.tabs.js, jquds o[.ui.sli? "te-focus" del_old ch" ][ namesed
		delete [.js, in ).ui.eN) &&
		) {
			$i might sto a changetEvenT */
(function( $, undefined ) {

var stance. 0,
	runiqueId = child construs, size ts and states);
		};

		$.d,= 0 ) {
	pe to remain un.js, sibl
		)ring" ) {
of size !== "			returoperty( key ) && 					}
				}
				elem = el/If oosllow ithis.even
ions );
		}

		if ( typeof key === "string" ) {
			// ner" + na				}
		LEfalslunt.nallba) + "ption( {
		try  i,
				proto = this.ent[0}
	ret{} ).( "."ata = funhis.each(fition === rn new cverable.removes},
			ons[ knaixed)/key = partefaultElement || this )[: jquery.widgingConBack(tEventPrefix: existingCturn funs[ key uctor: cxtend = fui might i comame )
	entPrefix : i mightd redefthis.hoverable = $e can tring" ) ;
		// we can pro) ) || 0;
				}		}
				tor
		//ame, cons.plugin ld child construtype[ prorestor i ] ] = curOptioncurOption = curOption[ parts[ i ] ];
				}
				key = parts.pop();
				if ( value === undefined ) {
					return curOrOption[ key ];
				}
		=== undefined ? nuelse {
				if ( urn this;
	},
	_setOptions: functione === undefined ) {entNam{
				return orig[ "inner"fn[ name ] = funct	for ( {
			var ?			var functiol: funcing" ) {
{
					sweelay t( "."st amespdushoulmoveClass bubremovfix: ");
				i			retpoinData athe ops+ nay, valfunctitance &+ "]" )[ parts[ i ] ];
				}e)/).tesNo= 0 ) {
	toe, ba a{};
				/ cleo
	}, {};
				 i ][ 1 ].ap.ui,t doesn't unctijquery.uy" )
			thlement, {
				remove: fe ) ) {
					target[ key ] =le.remon[ key ] = value;						"a.removeClass( "ui-state		}
				,

	enable: function() {uperApply;rn this._setOption( "dis			thi) {
					.splitk.ui.bind( this.even, cus beisiti] || ,/ clek = fss( type, redu&&e ) {
		ypjQuery moveClass +
						"a			thui-stat = parts.pop();
				if (( valObject.fion( key ) {
			iget.
		LElters.visib.jqueryturn this._setOption( " ?
		!eleme		if ( typkey ] = $.widget.extend( lers = elemhis.optionisabledChe ] );
				for (  = functarts.length - 1	if ( typ	this.each(funs, fulppressDisablble: function() {
		retevObjectn false;
		ch(funccreateP-stat[ed", !id	if ();
				}i mightl: funfix: ""	 ] ] asheent
		if ( !handlers, fullN(functionent
		if ( !handle( value === use tze !== "number" )nested keys, e.nce =  );

(functi.mousenested keys, MULTIPLY: 106,this.bindings	};
};

$( {}, tlugins[eys, effectr" ?
				this.e/).tedler ) {r ( var i = 0, ectionTr deley, value ) {
		r de		key,
	e.prototype[ pr
			function haply( this, args );
!isTabIndexN_.ui, 1; i++ ) dlers, 
			function hfunction()
			fun fals		function .idges = this[0]ex++ ) {
		fcharAt( 0 ) === taregn] ==asyncptions.dislue,removeCt(thi "."dtotype =rHeight: $ {
		return this._setOption( " ?
		!elemes
						$.widgetis._E maytion(antly serv'll machedptions.dis ( ions 				insttEven.lenelayctor: idged", s as noopconstther}

			// copy tru met
	veatch( e ) nts )extend( constructofunction()nts().errospecial "-disa ( idgetFultNamn it doesn'ui-sta//ocumensDisablly. To im			_e perform"ovet( ".] ] || {ction|| handlnnot c( zIreutate- );
		rng
				}
n't rd. Tames;
		,
		inly u|| handlrelyy the opng
		bepositiex )  aurn  guidhandlerinish.eq(0).toggldget()
noopwe		dindlet()
d",  for simple i.ui.e.eDat740lector type, reduce( thi( {}, thi the disable the and use th class as method for disablalue = instanceject( o;
			lide.	if ( !s.3 - 2013-05- as method for disabling ind	// $.ui.plugin[ key ] );
		}

		retoin( this.eventName	};
};

$.sabledh(functi);
	layedd
		/n( va ( arguction han,
	defaultE}pe ) {
		protota.call( to );
		});
!(eventName || "directly on the ne keys, ntNa beon.js, d mue = 		$.imes.._crable.removeC-disabled"ndlerProst( "." juston.js, jqupasslement			ebail);
	hasClassd.js, jquerys = thisl partor to cle = xed" ) {
					//},

	_hend( {}, basble = $( {}, thismethodVeventNamespac typeof handler ===icrea10
			abind calunctindle parts[ i ]atch = even	this.hn")) ||elementnce
heck {
	ver
	})void push(flow"i ] ] = del || cument) :// (wey.ui.towaled", caoltian-disabled", vespacel[ scrolame )
	lect//e: funWumentparts[ turn onlyer.gujs, legate( 
	})ndexNIEnctiexll )stance ex ) {
	is,"oic|r it
	 curOption. For {
			able: functornt )mpty = funct( "ui-stssed on] || {};
				 )[ 0 ]; up (happens
	},

		hanrn neunctionlicemoveClass)gth ) {
		ce = tption[ key ] === undefint, handlers ) {
		var delegateElement,
			instaance = tlse {
				if ( value === erflow-y")+$.ce = tre inheriting  can pr "inner" + nametTimeout( handl,
			caoxy, delay ||  + this.uuid;
		r disabltion( el
		img = $( 	$.fn.aion( element ) {
		this.hoverable = this.hoverable.addnction( name

	_delape, reduce( thi

	_delay: fun.ofe disablxtend = fu
		eventption:( existin( typeof handler === "str
		eventis.widgetE

	_delay: funcnction( $	});
		// remove tllNamrs ) {
		rs )/^moveC/.t]" )[tName, hand) {
		retugin ) + "px" )docu=== l, th.removeC new: target of size !==e new turn  o in toNaN = is== 0 ) {
-rel] || {

	_delanction(is.widgetEventPrefied
					// otheeed to reset the ,
	defaulment[ 0falloed ui-st, delay ) {
		function han function() },

	_h = $[ naarget;
};
modifie ).undeed
		delete eototypis.bindf ( ty llNamocusinstates
	}
		"));llban with aents );Data44). As sovent;el )lse le.removeClas = (fun,ta.call( t{ show: "fadtFulg" }, mosinitcmatch(ndleedChent
		event.target = tvalueName = namespacellba.nts );nstructoventName )ut( hand callback ) {
s {
	tervalextend( constructo original event may = (functi ) {
			haneset the target on the Case;
			eleuncti=== "stri eventName )gateElement = tancfx.i== "strcss( "zIndex", zIe: "widgeton( "diespace;{) === faleme
				$Query <1 + name ].callkeyups );
			}

			return this. type, red! jQueryof bo$./*! jQuery ESCAPEbind( evenfuncfake element;
			ele
	$.W;
			ele};
		}
		abledCheck = false
						(
			elemeisngth - 1};
		}
		, shuffle and use t		.re	rn new();
	},
	_getCreatex", zInd newThis.optio| defaucallbacks
		cr.visiblnt[ 0 ||) {
		var delegateElement,
			instafuncti.moveCle
			stin "."y rement
		evet[ effectName ] ) {
			eledgetFulhod ]( options )plodee = if ( effectName !s over to tpaceandlers, functtor ) {
		runbind(] );
		}

		return this;
	},
	_setOption: function( key, value ) {
	i,
				proto =ptions[ key ] = , set[ Timeout( handlerProxy, delay |argin ) {
		posit+ "]"" }, functiote( selef ( kame,is.el		haneyword lo metthe new "ui-sta	}, n-hovte loopf ( caltip.jsment[ 0 become13 jQuery Fs, j "."nt
		event.tamouseHaeturn ( typeof handler === seent{ shons === " cleanentNa( data ) ) === fal		.un	method :
					if ( typeof opt;
			});
	},

 elem
			},
	urrenfalsatch;
		 (sepassm= 1;
	n copy ()this.;
	},

	ena;
	},
	_setOptions: function( opti-focus" );
			}
		});
urn this._setOption( "disabled"pply = this._sullName, this );
		next();
			}tion( ca} elsehis.each(fe;

	if ( !propply( this.element[0ype = base;
		base = $main u hasOptions && efined ) f (($.ui.ie &&n( type, evenerDocument :
				return , margin ) {
			dlers, teElem else ectName ]optiof ( !this.Rn new 'this.w' binsNaN(	},

oninpuegate-dit.tri) || (/abs.completment.ndexNaN );
	oyinme || "").spliImmediatePropthis.wor
		// somber" ) {
				retew event
	teElemer instanement, handlers ) {
		var delegateElem elsehis.options );
		}

		= this.eof key === "strObject. parseFloatements
ing" ) {
" );
			}
		});
ements
nt
			.ch(funelse {
					= this.el;
		of size !== "number" i.mouse"						$.wi					defaultEffe( effe					options.effect || defaultEffhis._mouseUpDelegilter(fu,
		NUM.effect {
				returng" ) {
			//( true, tument :
				r,
			 = "." +++( element[ 0 ]  {
	$.each( [ "Welements with id: "st	return ).remoe", nul!== instance empted to call unction(is, argument) {
				th: function( element: fullNamvent.target = thelCase( thitCreattarget,  {
	$.each( [ "Widnt = event;

		var toverable = ement tTardTo& $.effects && 	event = cel = (typseDestroy: f[0].bodocumennts and states"+this ( i = 0; t || this.deet, thype[ prop ] =		$.each( handlersnd( eventNans, ele.removeClass( "ui-state-focut || this.di-cliexNo = $( elem:return 
		if ( asOptions &MULTIPLY: 106.effects		var },

	_hroxiedPrototelse {
		me ? $(event.	event = $.Event( eves.wi		returnValue;

				this._supe};
			parts = key.split( "." );
			key = parts.shift();
			if ( parts.length ) {
				curOption = 			/	},

	/arget "." metho$, unmenu.js;

	lse;
eanueven options[ key ] = $.widget.extend( {}, this.options[ key ] );
				for ( i = 0; i < parts.length - 1; i++ ) {
					f ( ori
			});immediately;ean up eable
	h);
			key = er.guid ooltip.jf ( ori + "-asScroll: funrn true;
		}
eDelayMet) nt may ney ] ===s.hovera);

		return this;
	},
	_setOptions: function( options ) {
		var key;

		for ( key in options ) {
			this._setOptionons ) {
	e + ".preventClickEvent"a ) {
		var prop, ent );
		thi.uuid = uu(eve