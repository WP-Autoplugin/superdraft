(function ($) {
	'use strict';

	$( document ).ready(
		function () {
			const $button      = $( '#superdraft-suggest-terms' );
			const $spinner     = $button.siblings( '.spinner' );
			const $suggestions = $( '#superdraft-term-suggestions' );
			let generatedTerms = [];

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
								taxonomy: window.location.href.includes( 'taxonomy=post_tag' ) ? 'post_tag' : 'category',
								generatedTerms: generatedTerms
							},
							success: function (response) {
								if (response.success && Array.isArray( response.data )) {
									// Update button text after first successful response
									$button.text( 'Generate More' ); // Todo: i18n

									// Store new terms
									generatedTerms = generatedTerms.concat( response.data );

									// Append new suggestions to existing ones
									response.data.forEach(
										function (term) {
											const $termEl = $( '<div class="term-suggestion">' )
											.append(
												$( '<span>' ).text( term ),
												$( '<button type="button" class="button-link">' )
													.text( 'Add' )
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
								}
							},
							error: function () {
								$suggestions.html( '<p class="error">Failed to get suggestions</p>' );
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
