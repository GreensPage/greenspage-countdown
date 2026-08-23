/**
 * Greenspage Countdown - block editor UI.
 *
 * No build step: plain wp.element.createElement, no JSX, no bundler.
 */
( function ( blocks, element, blockEditor, components, i18n ) {
	'use strict';

	var el = element.createElement;
	var Fragment = element.Fragment;
	var useEffect = element.useEffect;
	var useState = element.useState;
	var __ = i18n.__;

	var InspectorControls = blockEditor.InspectorControls;
	var useBlockProps = blockEditor.useBlockProps;
	var ColorPalette = blockEditor.ColorPalette || components.ColorPalette;

	var PanelBody = components.PanelBody;
	var SelectControl = components.SelectControl;
	var TextControl = components.TextControl;
	var TextareaControl = components.TextareaControl;
	var ToggleControl = components.ToggleControl;
	var CheckboxControl = components.CheckboxControl;
	var RangeControl = components.RangeControl;
	var BaseControl = components.BaseControl;

	var DATA = window.GPCD_DATA || { countries: [], allTimezones: [], siteTimezone: 'UTC', guessCountry: '' };
	var ORDER = [ 'days', 'hours', 'minutes', 'seconds' ];
	var MS = { days: 86400000, hours: 3600000, minutes: 60000, seconds: 1000 };

	function uid() {
		return 'cd' + Math.random().toString( 36 ).slice( 2, 10 );
	}

	/**
	 * Offset, in ms, between a moment and how that moment reads in a given zone.
	 */
	function tzOffset( date, tz ) {
		try {
			var dtf = new Intl.DateTimeFormat( 'en-US', {
				timeZone: tz,
				hour12: false,
				year: 'numeric', month: '2-digit', day: '2-digit',
				hour: '2-digit', minute: '2-digit', second: '2-digit'
			} );

			var p = {};
			dtf.formatToParts( date ).forEach( function ( part ) {
				p[ part.type ] = part.value;
			} );

			var asUtc = Date.UTC( p.year, p.month - 1, p.day, p.hour % 24, p.minute, p.second );
			return asUtc - date.getTime();
		} catch ( e ) {
			return 0;
		}
	}

	/**
	 * Turn a wall-clock string ("2026-09-04T10:00") in a zone into a real timestamp.
	 * Two passes so daylight-saving boundaries land on the right side.
	 */
	function zonedToTimestamp( local, tz ) {
		if ( ! local ) {
			return NaN;
		}

		var naive = Date.parse( local.length === 16 ? local + ':00Z' : local + 'Z' );

		if ( isNaN( naive ) ) {
			return NaN;
		}

		var ts = naive - tzOffset( new Date( naive ), tz );
		return naive - tzOffset( new Date( ts ), tz );
	}

	function findCountry( code ) {
		for ( var i = 0; i < DATA.countries.length; i++ ) {
			if ( DATA.countries[ i ].code === code ) {
				return DATA.countries[ i ];
			}
		}
		return null;
	}

	function countryOptions() {
		var opts = [ { label: __( 'Use site setting', 'greenspage-countdown' ), value: '' } ];

		DATA.countries.forEach( function ( c ) {
			opts.push( { label: c.label, value: c.code } );
		} );

		opts.push( { label: __( 'Other / show every timezone', 'greenspage-countdown' ), value: '__all' } );
		return opts;
	}

	function timezoneOptions( country ) {
		var list;

		if ( '__all' === country ) {
			list = DATA.allTimezones;
		} else {
			var found = findCountry( country );
			list = found ? found.timezones : [ DATA.siteTimezone ];
		}

		var opts = [
			{
				label: __( 'Site timezone', 'greenspage-countdown' ) + ' (' + DATA.siteTimezone + ')',
				value: ''
			}
		];

		( list || [] ).forEach( function ( tz ) {
			opts.push( { label: tz.replace( /_/g, ' ' ), value: tz } );
		} );

		return opts;
	}

	function prettyTarget( local, tz ) {
		var ts = zonedToTimestamp( local, tz );

		if ( isNaN( ts ) ) {
			return '';
		}

		try {
			return new Intl.DateTimeFormat( undefined, {
				timeZone: tz,
				weekday: 'long', day: 'numeric', month: 'long', year: 'numeric',
				hour: 'numeric', minute: '2-digit'
			} ).format( new Date( ts ) ) + ' (' + tz + ')';
		} catch ( e ) {
			return new Date( ts ).toString();
		}
	}

	function splitDiff( diff, units ) {
		var active = ORDER.filter( function ( u ) {
			return units.indexOf( u ) !== -1;
		} );

		if ( ! active.length ) {
			active = ORDER.slice();
		}

		var out = {};
		var rest = diff;

		active.forEach( function ( unit ) {
			out[ unit ] = Math.floor( rest / MS[ unit ] );
			rest -= out[ unit ] * MS[ unit ];
		} );

		return out;
	}

	function pad( n ) {
		n = Math.max( 0, Math.floor( n ) );
		return n < 10 ? '0' + n : String( n );
	}

	function Edit( props ) {
		var a = props.attributes;
		var set = props.setAttributes;
		var tz = a.timezone || DATA.siteTimezone;
		var isEvergreen = 'evergreen' === a.mode;

		var tickState = useState( 0 );
		var setTick = tickState[ 1 ];

		useEffect( function () {
			if ( ! a.uid ) {
				set( { uid: uid() } );
			}

			if ( ! a.country && DATA.guessCountry ) {
				set( { country: DATA.guessCountry } );
			}
		}, [] );

		useEffect( function () {
			var id = window.setInterval( function () {
				setTick( function ( n ) {
					return n + 1;
				} );
			}, 1000 );

			return function () {
				window.clearInterval( id );
			};
		}, [] );

		var units = ( a.units && a.units.length ) ? a.units : ORDER.slice();

		var labels = {
			days: a.labelDays,
			hours: a.labelHours,
			minutes: a.labelMinutes,
			seconds: a.labelSeconds
		};

		// Live preview numbers.
		var parts = null;
		var targetTs = NaN;

		if ( isEvergreen ) {
			parts = splitDiff( Math.max( 0, ( a.evergreenHours || 0 ) * 3600000 ), units );
		} else if ( a.targetDate ) {
			targetTs = zonedToTimestamp( a.targetDate, tz );
			if ( ! isNaN( targetTs ) ) {
				parts = splitDiff( Math.max( 0, targetTs - Date.now() ), units );
			}
		}

		function toggleUnit( unit, on ) {
			var next = ORDER.filter( function ( u ) {
				if ( u === unit ) {
					return on;
				}
				return units.indexOf( u ) !== -1;
			} );

			set( { units: next.length ? next : [ 'days' ] } );
		}

		var inspector = el(
			InspectorControls,
			null,

			el(
				PanelBody,
				{ title: __( 'Countdown', 'greenspage-countdown' ), initialOpen: true },

				el( SelectControl, {
					label: __( 'What is it counting?', 'greenspage-countdown' ),
					value: a.mode,
					options: [
						{ label: __( 'A fixed date and time', 'greenspage-countdown' ), value: 'date' },
						{ label: __( 'A rolling timer per visitor (evergreen)', 'greenspage-countdown' ), value: 'evergreen' }
					],
					onChange: function ( v ) {
						set( { mode: v } );
					},
					help: isEvergreen
						? __( 'Each visitor gets their own clock, starting the first time they see this block.', 'greenspage-countdown' )
						: __( 'Everyone sees the same deadline.', 'greenspage-countdown' )
				} ),

				! isEvergreen && el( SelectControl, {
					label: __( 'Country', 'greenspage-countdown' ),
					value: a.country,
					options: countryOptions(),
					onChange: function ( v ) {
						var found = findCountry( v );
						var patch = { country: v, timezone: '' };

						if ( found ) {
							patch.dateFormat = found.format;

							if ( 1 === found.timezones.length ) {
								patch.timezone = found.timezones[ 0 ];
							}
						}

						set( patch );
					},
					help: __( 'Sets the date format and narrows the timezone list.', 'greenspage-countdown' )
				} ),

				! isEvergreen && el( SelectControl, {
					label: __( 'Timezone', 'greenspage-countdown' ),
					value: a.timezone,
					options: timezoneOptions( a.country ),
					onChange: function ( v ) {
						set( { timezone: v } );
					}
				} ),

				! isEvergreen && el( TextControl, {
					label: __( 'Date and time', 'greenspage-countdown' ),
					type: 'datetime-local',
					value: a.targetDate,
					onChange: function ( v ) {
						set( { targetDate: v } );
					}
				} ),

				! isEvergreen && a.targetDate && el(
					'p',
					{ className: 'gp-cd-editor__notice' },
					prettyTarget( a.targetDate, tz )
				),

				isEvergreen && el( TextControl, {
					label: __( 'Hours on the clock', 'greenspage-countdown' ),
					type: 'number',
					min: 0.25,
					step: 0.25,
					value: a.evergreenHours,
					onChange: function ( v ) {
						set( { evergreenHours: parseFloat( v ) || 0 } );
					},
					help: __( 'How long each visitor gets from their first view.', 'greenspage-countdown' )
				} ),

				isEvergreen && el( TextControl, {
					label: __( 'Start over after (days)', 'greenspage-countdown' ),
					type: 'number',
					min: 0,
					step: 1,
					value: a.evergreenResetDays,
					onChange: function ( v ) {
						set( { evergreenResetDays: parseInt( v, 10 ) || 0 } );
					},
					help: __( '0 means it never restarts for that visitor.', 'greenspage-countdown' )
				} )
			),

			el(
				PanelBody,
				{ title: __( 'When it runs out', 'greenspage-countdown' ), initialOpen: false },

				el( SelectControl, {
					value: a.onExpire,
					options: [
						{ label: __( 'Sit at zero', 'greenspage-countdown' ), value: 'freeze' },
						{ label: __( 'Hide the countdown', 'greenspage-countdown' ), value: 'hide' },
						{ label: __( 'Swap in a message', 'greenspage-countdown' ), value: 'message' },
						{ label: __( 'Send the visitor to a URL', 'greenspage-countdown' ), value: 'redirect' }
					],
					onChange: function ( v ) {
						set( { onExpire: v } );
					}
				} ),

				'message' === a.onExpire && el( TextareaControl, {
					label: __( 'Message', 'greenspage-countdown' ),
					value: a.expireMessage,
					onChange: function ( v ) {
						set( { expireMessage: v } );
					},
					help: __( 'Tokens: {date}, {time}, {weekday}', 'greenspage-countdown' )
				} ),

				'redirect' === a.onExpire && el( TextControl, {
					label: __( 'Redirect to', 'greenspage-countdown' ),
					type: 'url',
					value: a.redirectUrl,
					placeholder: 'https://',
					onChange: function ( v ) {
						set( { redirectUrl: v } );
					}
				} )
			),

			el(
				PanelBody,
				{ title: __( 'Display', 'greenspage-countdown' ), initialOpen: false },

				el( SelectControl, {
					label: __( 'Layout', 'greenspage-countdown' ),
					value: a.layout,
					options: [
						{ label: __( 'Boxes', 'greenspage-countdown' ), value: 'boxes' },
						{ label: __( 'Inline', 'greenspage-countdown' ), value: 'inline' }
					],
					onChange: function ( v ) {
						set( { layout: v } );
					}
				} ),

				el( TextControl, {
					label: __( 'Intro line', 'greenspage-countdown' ),
					value: a.introText,
					placeholder: __( 'Coming {weekday}, {date}', 'greenspage-countdown' ),
					onChange: function ( v ) {
						set( { introText: v } );
					},
					help: __( 'Tokens: {date}, {time}, {weekday}. Keeps your copy honest when the date moves.', 'greenspage-countdown' )
				} ),

				el(
					BaseControl,
					{ label: __( 'Units to show', 'greenspage-countdown' ) },
					ORDER.map( function ( unit ) {
						return el( CheckboxControl, {
							key: unit,
							label: labels[ unit ],
							checked: units.indexOf( unit ) !== -1,
							onChange: function ( on ) {
								toggleUnit( unit, on );
							}
						} );
					} )
				),

				el( TextControl, {
					label: __( 'Label: days', 'greenspage-countdown' ),
					value: a.labelDays,
					onChange: function ( v ) { set( { labelDays: v } ); }
				} ),
				el( TextControl, {
					label: __( 'Label: hours', 'greenspage-countdown' ),
					value: a.labelHours,
					onChange: function ( v ) { set( { labelHours: v } ); }
				} ),
				el( TextControl, {
					label: __( 'Label: minutes', 'greenspage-countdown' ),
					value: a.labelMinutes,
					onChange: function ( v ) { set( { labelMinutes: v } ); }
				} ),
				el( TextControl, {
					label: __( 'Label: seconds', 'greenspage-countdown' ),
					value: a.labelSeconds,
					onChange: function ( v ) { set( { labelSeconds: v } ); }
				} ),

				el( ToggleControl, {
					label: __( 'Show a progress bar', 'greenspage-countdown' ),
					checked: !! a.showProgress,
					onChange: function ( v ) {
						set( { showProgress: v } );
					}
				} ),

				a.showProgress && ! isEvergreen && el( TextControl, {
					label: __( 'Progress starts at', 'greenspage-countdown' ),
					type: 'datetime-local',
					value: a.progressFrom,
					onChange: function ( v ) {
						set( { progressFrom: v } );
					},
					help: __( 'Leave blank to start from the day this page was published.', 'greenspage-countdown' )
				} ),

				ColorPalette && el(
					BaseControl,
					{ label: __( 'Accent colour', 'greenspage-countdown' ) },
					el( ColorPalette, {
						value: a.accentColor,
						onChange: function ( v ) {
							set( { accentColor: v || '' } );
						}
					} )
				),

				! isEvergreen && el( TextControl, {
					label: __( 'Date format override', 'greenspage-countdown' ),
					value: a.dateFormat,
					onChange: function ( v ) {
						set( { dateFormat: v } );
					},
					help: __( 'PHP date format. Blank uses the country default.', 'greenspage-countdown' )
				} )
			)
		);

		var blockProps = useBlockProps( {
			className: 'gp-cd gp-cd--' + a.layout + ( a.showProgress ? ' gp-cd--has-progress' : '' ),
			style: a.accentColor ? { '--gp-cd-accent': a.accentColor } : undefined
		} );

		var needsDate = ! isEvergreen && ! a.targetDate;

		var preview = el(
			'div',
			blockProps,

			a.introText && el(
				'p',
				{ className: 'gp-cd__intro' },
				a.introText
					.replace( '{weekday}', isNaN( targetTs ) ? '' : new Date( targetTs ).toLocaleDateString( undefined, { weekday: 'long', timeZone: tz } ) )
					.replace( '{date}', isNaN( targetTs ) ? '' : new Date( targetTs ).toLocaleDateString( undefined, { day: 'numeric', month: 'long', year: 'numeric', timeZone: tz } ) )
					.replace( '{time}', isNaN( targetTs ) ? '' : new Date( targetTs ).toLocaleTimeString( undefined, { hour: 'numeric', minute: '2-digit', timeZone: tz } ) )
			),

			needsDate
				? el( 'div', { className: 'gp-cd-editor__empty' }, __( 'Pick a date and time in the sidebar.', 'greenspage-countdown' ) )
				: el(
					'div',
					{ className: 'gp-cd__units' },
					units.map( function ( unit ) {
						return el(
							'div',
							{ className: 'gp-cd__unit', key: unit },
							el( 'span', { className: 'gp-cd__num' }, parts ? pad( parts[ unit ] ) : '--' ),
							el( 'span', { className: 'gp-cd__label' }, labels[ unit ] )
						);
					} )
				),

			a.showProgress && el(
				'div',
				{ className: 'gp-cd__progress' },
				el( 'span', { className: 'gp-cd__bar', style: { width: '35%' } } )
			),

			isEvergreen && el(
				'p',
				{ className: 'gp-cd-editor__preview-note' },
				__( 'Evergreen: each visitor starts their own clock at this length.', 'greenspage-countdown' )
			)
		);

		return el( Fragment, null, inspector, preview );
	}

	blocks.registerBlockType( 'greenspage/countdown', {
		edit: Edit,
		save: function () {
			return null;
		}
	} );
}(
	window.wp.blocks,
	window.wp.element,
	window.wp.blockEditor,
	window.wp.components,
	window.wp.i18n
) );
