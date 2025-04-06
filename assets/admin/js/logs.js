jQuery( document ).ready(
	function ($) {
		function unescapeString(str) {
			try {
				return JSON.parse( str );
			} catch (e) {
				return str;
			}
		}

		function escapeHTML(str) {
			return str.replace(
				/[&<>"']/g,
				function (match) {
					const escape = {
						'&': '&amp;',
						'<': '&lt;',
						'>': '&gt;',
						'"': '&quot;',
						"'": '&#39;'
					};
					return escape[match];
				}
			);
		}

		function formatJSON(obj, level = 0) {
			if (obj === null) {
				return '<span class="json-value">null</span>';
			}
			if (obj === '') {
				return '<span class="json-string">""</span>';
			}
			if ( ! obj) {
				return '';
			}

			const indent = '    '.repeat( level );
			let html     = level === 0 ? '' : '<br>';

			if (Array.isArray( obj )) {
				html     += indent + '[\n';
				obj.forEach(
					(item, index) => {
						html += indent + formatJSON( item, level + 1 );
						if (index < obj.length - 1) {
							html += ',';
						}
						html += '\n';
					}
				);
				html += indent + ']';
			} else if (typeof obj === 'object') {
				html                   += indent + '{\n';
				const entries           = Object.entries( obj );
				entries.forEach(
					([key, value], index) => {
						const isSimpleValue = ((typeof value === 'string' && ! value.includes( '\n' )) ||
									Number.isInteger( value ) ||
									value === null ||
									value === '');
					if (isSimpleValue) {
						const valueClass = typeof value === 'string' ? 'json-string' : 'json-value';
						let displayValue;
						if (value === null) {
							displayValue = 'null';
						} else if (value === '') {
							displayValue = '""';
						} else {
							displayValue = typeof value === 'string' ? `"${escapeHTML(value)}"` : value;
						}
						html += `${indent}<span class="json-key">${key}</span>: <span class="${valueClass}">${displayValue}</span>`;
					} else {
						html += `${indent}<span class="json-key">${key}</span>: ${formatJSON( value, level + 1 )}`;
					}
					if (index < entries.length - 1) {
						html += ',';
					}
					html += '\n';
					}
				);
				html += indent + '}';
			} else {
				const valueClass = typeof obj === 'string' ? 'json-string' : 'json-value';
				let displayValue;
				if (typeof obj === 'string') {
					try {
						// Check if the string is actually a stringified object/array
						const parsed = JSON.parse( obj );
						if (typeof parsed === 'object' && parsed !== null) {
							return formatJSON( parsed, level );
						}
						displayValue = `"${escapeHTML(obj)}"`;
					} catch (e) {
						displayValue = `"${escapeHTML(obj)}"`;
					}
				} else {
					displayValue = JSON.stringify( obj );
				}
				html += `<span class="${valueClass}">${displayValue}</span>`;
			}
			return html;
		}

		// Create popup elements if they don't exist
		if ( ! $( '.superdraft-message-popup' ).length) {
			$('body').append(
				`
				<div class="superdraft-message-popup-overlay"></div>
				<div class="superdraft-message-popup">
				<span class="superdraft-message-popup-close">&times;</span>
				<pre class="superdraft-message-popup-content"></pre>
				</div>
				`
			);
		}

		// Handle click on "View Message" link
		$( document ).on(
			'click',
			'.superdraft-view-message',
			function (e) {
				e.preventDefault();
				let message = $( this ).data( 'message' );
				if (typeof message === 'object') {
					$( '.superdraft-message-popup-content' ).html( formatJSON( message ) );
				} else {
					$( '.superdraft-message-popup-content' ).text( message );
				}
				$( '.superdraft-message-popup-overlay, .superdraft-message-popup' ).show();
			}
		);

		// Close popup when clicking close button or overlay
		$( '.superdraft-message-popup-close, .superdraft-message-popup-overlay' ).on(
			'click',
			function () {
				$( '.superdraft-message-popup-overlay, .superdraft-message-popup' ).hide();
			}
		);

		// Close popup when pressing ESC
		$( document ).keydown(
			function (e) {
				if (e.key === "Escape") {
					$( '.superdraft-message-popup-overlay, .superdraft-message-popup' ).hide();
				}
			}
		);
	}
);
