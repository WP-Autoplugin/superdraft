(function ($) {
	'use strict';

	const { __ } = wp.i18n;

	$( document ).ready(
		function () {
			const $button      = $( '#superdraft-suggest-terms' );
			const $spinner     = $button.siblings( '.spinner' );
			const $suggestions = $( '#superdraft-term-suggestions' );
			const urlParams    = new URLSearchParams( window.location.search );
			const taxonomy     = $button.data( 'taxonomy' ) || urlParams.get( 'taxonomy' ) || 'category';
			let generatedTerms = [];

			function getErrorMessage(response) {
				if (response && response.data) {
					if ('string' === typeof response.data) {
						return response.data;
					}

					if (response.data.message) {
						return response.data.message;
					}
				}

				return 'Failed to get suggestions';
			}

			$button.on(
				'click',
				function () {
					$button.prop( 'disabled', true );
					$spinner.addClass( 'is-active' );
					// Removed: $suggestions.empty();

					$.ajax(
						{
							url: superdraftTax.ajaxurl,
							type: 'POST',
							data: {
								action: 'superdraft_suggest_terms',
								nonce: superdraftTax.nonce,
								taxonomy: taxonomy,
								generatedTerms: generatedTerms
							},
							success: function (response) {
								if (response.success && Array.isArray( response.data )) {
									// Update button text after first successful response
									$button.text( __( 'Generate More', 'superdraft' ) );

									// Store new terms
									generatedTerms = generatedTerms.concat( response.data );

									// Append new suggestions to existing ones
									response.data.forEach(
										function (term) {
											const $termEl = $( '<div class="term-suggestion">' )
											.append(
												$( '<span>' ).text( term ),
												$( '<button type="button" class="button-link">' )
													.text( __( 'Add', 'superdraft' ) )
													.on(
														'click',
														function () {
															// Fill the "Add new category/tag" form
															$( '#tag-name' ).val( term );
															$( '#submit' ).click();
															$( this ).closest( '.term-suggestion' ).remove();
															// Remove term from generatedTerms when added
															generatedTerms = generatedTerms.filter( t => t !== term );
														}
													)
											);
											$suggestions.append( $termEl );
										}
									);
								} else {
									$suggestions.html( $( '<p class="error">' ).text( getErrorMessage( response ) ) );
								}
							},
							error: function () {
								$suggestions.html( '<p class="error">' + __( 'Failed to get suggestions', 'superdraft' ) + '</p>' );
							},
							complete: function () {
								$button.prop( 'disabled', false );
								$spinner.removeClass( 'is-active' );
							}
						}
					);
				}
			);

			// Move the original submit button above the div.form-field.term-suggest-wrap
			$( '#submit' ).insertBefore( '.form-field.term-suggest-wrap' );
		}
	);
})( jQuery );
