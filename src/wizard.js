/**
 * Superdraft Setup Wizard JavaScript
 *
 * @package Superdraft
 * @since 1.1.5
 */

(function($) {
	'use strict';

	var Wizard = {
		currentStep: 1,
		totalSteps: 4,
		selectedProvider: '',
		apiKey: '',
		connectionTested: false,
		connectionAdvanceTimer: null,
		enabledModules: {},
		currentSlide: 0,
		totalSlides: 0,

		init: function() {
			this.bindEvents();
			this.updateProgressBar();
			this.initModules();
			this.initCarousel();
		},

		bindEvents: function() {
			// Provider selection.
			$(document).on('click', '.superdraft-wizard-provider-card', function(e) {
				$('.superdraft-wizard-provider-card').removeClass('selected');
				$(this).addClass('selected');
				Wizard.selectedProvider = $(this).data('provider');
				Wizard.updateKeyHint();
				Wizard.updateKeyLinks();
				Wizard.populateSavedApiKey();
				Wizard.enableNextButton();
			});

			// API key input.
			$(document).on('input', '#superdraft-wizard-api-key', function() {
				Wizard.apiKey = $(this).val().trim();
				Wizard.resetConnectionState();
				Wizard.enableNextButton();
			});

			// Navigation buttons.
			$(document).on('click', '.superdraft-wizard-next', function(e) {
				e.preventDefault();
				Wizard.nextStep($(this));
			});

			$(document).on('click', '.superdraft-wizard-prev', function(e) {
				e.preventDefault();
				Wizard.prevStep();
			});

			// Test connection.
			$(document).on('click', '.superdraft-wizard-test-btn', function(e) {
				e.preventDefault();
				Wizard.testConnection();
			});

			// Create demo post.
			$(document).on('click', '.superdraft-wizard-create-demo', function(e) {
				e.preventDefault();
				Wizard.createDemoPost();
			});

			// Dismiss wizard.
			$(document).on('click', '.superdraft-wizard-dismiss', function(e) {
				e.preventDefault();
				Wizard.dismissWizard();
			});

			// Finish wizard.
			$(document).on('click', '.superdraft-wizard-finish', function(e) {
				Wizard.dismissWizard();
			});

			// Module toggles.
			$(document).on('change', '.module-checkbox', function() {
				var $card = $(this).closest('.superdraft-wizard-module-card');
				var module = $card.data('module');
				var isEnabled = $(this).is(':checked');
				Wizard.enabledModules[module] = isEnabled;
				$card.toggleClass('enabled', isEnabled);
				$card.find('.toggle-status').text(isEnabled ? 'Enabled' : 'Disabled');
			});

			// Carousel navigation.
			$(document).on('click', '.carousel-prev', function(e) {
				e.preventDefault();
				Wizard.prevSlide();
			});

			$(document).on('click', '.carousel-next', function(e) {
				e.preventDefault();
				Wizard.nextSlide();
			});

			$(document).on('click', '.carousel-dot', function(e) {
				e.preventDefault();
				var slide = $(this).data('slide');
				Wizard.goToSlide(slide);
			});
		},

		initModules: function() {
			// Initialize default enabled state.
			$('.module-checkbox').each(function() {
				var $card = $(this).closest('.superdraft-wizard-module-card');
				var module = $card.data('module');
				var isEnabled = $(this).is(':checked');
				Wizard.enabledModules[module] = isEnabled;
				$card.toggleClass('enabled', isEnabled);
				$card.find('.toggle-status').text(isEnabled ? 'Enabled' : 'Disabled');
			});
		},

		initCarousel: function() {
			var $cards = $('.carousel-slides .superdraft-wizard-module-card');
			this.totalSlides = $cards.length;
			if (this.totalSlides > 1) {
				// Wrap cards in a sliding container
				var $wrapper = $('<div class="carousel-slide-wrapper"></div>');
				$cards.wrapAll($wrapper);
				// Set CSS variable for width calculation
				$wrapper.css('--total-slides', this.totalSlides);
				this.updateCarousel();
			} else {
				$('.carousel-nav').hide();
				$('.carousel-dots').hide();
			}
		},

		showSlide: function(index) {
			if (index < 0) {
				index = this.totalSlides - 1;
			} else if (index >= this.totalSlides) {
				index = 0;
			}
			this.currentSlide = index;
			this.updateCarousel();
		},

		nextSlide: function() {
			this.showSlide(this.currentSlide + 1);
		},

		prevSlide: function() {
			this.showSlide(this.currentSlide - 1);
		},

		goToSlide: function(index) {
			this.showSlide(index);
		},

		updateCarousel: function() {
			var $wrapper = $('.carousel-slide-wrapper');
			var offset = -this.currentSlide * 100;
			$wrapper.css('transform', 'translateX(' + offset + '%)');

			// Update dots
			$('.carousel-dot').removeClass('active').attr('aria-current', 'false');
			$('.carousel-dot[data-slide="' + this.currentSlide + '"]').addClass('active').attr('aria-current', 'true');

			// Update nav buttons
			$('.carousel-prev').prop('disabled', this.totalSlides <= 1);
			$('.carousel-next').prop('disabled', this.totalSlides <= 1);

			// Restart SVG animation on the active slide
			this.restartSvgAnimation();
		},

		restartSvgAnimation: function() {
			var $activeSlide = $('.carousel-slide-wrapper .superdraft-wizard-module-card').eq(this.currentSlide);
			var $svgContainer = $activeSlide.find('.module-preview-svg');
			var $svg = $svgContainer.find('svg');
			
			if ($svg.length) {
				// Clone the SVG element to restart all CSS animations
				var svgHtml = $svgContainer.html();
				$svgContainer.html('');
				$svgContainer.html(svgHtml);
			}
		},

		updateProgressBar: function() {
			$('.superdraft-wizard-progress-bar').attr('data-step', this.currentStep);
			$('.superdraft-wizard-step').removeClass('active completed');

			for (var i = 1; i <= this.totalSteps; i++) {
				var $step = $('.superdraft-wizard-step[data-step="' + i + '"]');
				if (i < this.currentStep) {
					$step.addClass('completed');
					$step.find('.step-number').text('✓');
				} else if (i === this.currentStep) {
					$step.addClass('active');
					$step.find('.step-number').text(i);
				} else {
					$step.find('.step-number').text(i);
				}
			}
		},

		showStep: function(step) {
			$('.superdraft-wizard-step-content').hide();
			$('.superdraft-wizard-step-content[data-step="' + step + '"]').show();
			this.currentStep = step;
			this.updateProgressBar();

			// Step-specific initialization.
			if (step === 2) {
				this.updateKeyHint();
				this.updateKeyLinks();
				this.populateSavedApiKey();
			} else if (step === 3) {
				this.clearConnectionAdvanceTimer();
			} else if (step === 4) {
				this.saveSettings();
				this.updateSummary();
			}
		},

		nextStep: function($trigger) {
			if (this.currentStep === 2 && !this.connectionTested) {
				if (this.apiKey) {
					this.testConnection($trigger);
				}
				return;
			}

			if (this.currentStep < this.totalSteps) {
				this.showStep(this.currentStep + 1);
			}
		},

		prevStep: function() {
			if (this.currentStep > 1) {
				this.showStep(this.currentStep - 1);
			}
		},

		updateKeyHint: function() {
			var hints = {
				'openai': 'sk-...',
				'anthropic': 'sk-ant-...',
				'google': 'Your Google API key',
				'xai': 'xai-...',
				'custom': 'Your custom API key'
			};
			$('#superdraft-wizard-api-key').attr('placeholder', hints[this.selectedProvider] || '');
		},

		updateKeyLinks: function() {
			$('.superdraft-wizard-key-links .key-link').removeClass('active');
			$('.superdraft-wizard-key-links .key-link[data-provider="' + this.selectedProvider + '"]').addClass('active');
		},

		populateSavedApiKey: function() {
			var savedKeys = typeof superdraftWizardApiKeys === 'object' && superdraftWizardApiKeys ? superdraftWizardApiKeys : {};
			var savedKey = savedKeys[this.selectedProvider] || '';

			this.apiKey = savedKey;
			$('#superdraft-wizard-api-key').val(savedKey);
			this.resetConnectionState();
			this.enableNextButton();
		},

		enableNextButton: function() {
			var $btn = $('.superdraft-wizard-step-content[data-step="' + this.currentStep + '"] .superdraft-wizard-next');

			if (this.currentStep === 1) {
				$btn.prop('disabled', !this.selectedProvider);
			} else if (this.currentStep === 2) {
				$btn.prop('disabled', !this.apiKey);
			} else if (this.currentStep === 3) {
				// Module step - always enabled since we have defaults.
				$btn.prop('disabled', false);
			}
		},

		disableNextButton: function() {
			$('.superdraft-wizard-step-content[data-step="' + this.currentStep + '"] .superdraft-wizard-next').prop('disabled', true);
		},

		clearConnectionAdvanceTimer: function() {
			if (this.connectionAdvanceTimer) {
				clearTimeout(this.connectionAdvanceTimer);
				this.connectionAdvanceTimer = null;
			}
		},

		resetConnectionState: function() {
			this.clearConnectionAdvanceTimer();
			this.connectionTested = false;
			$('.superdraft-wizard-test-result').hide().removeClass('success error');
		},

		updateSummary: function() {
			// Update the summary on the final step.
			$('.feature-card').each(function() {
				var module = $(this).data('module');
				if (Wizard.enabledModules[module]) {
					$(this).addClass('enabled').removeClass('disabled');
				} else {
					$(this).addClass('disabled').removeClass('enabled');
				}
			});
		},

		testConnection: function($trigger) {
			var $btn = $trigger && $trigger.length ? $trigger : $('.superdraft-wizard-test-btn');
			var $result = $('.superdraft-wizard-test-result');
			var originalText = $btn.data('original-text') || $btn.text();

			$btn.data('original-text', originalText);

			$btn.prop('disabled', true).text(superdraftWizard.i18n.testing || 'Testing...');
			$result.hide().removeClass('success error');

			$.ajax({
				url: superdraftWizard.ajax_url,
				type: 'POST',
				data: {
					action: 'superdraft_wizard_test_api',
					nonce: superdraftWizard.nonce,
					provider: this.selectedProvider,
					api_key: this.apiKey
				},
				success: function(response) {
					if (response.success) {
						$result.addClass('success')
							.find('.test-result-icon').text('✓');
						$result.find('.test-result-message').text(response.data || superdraftWizard.i18n.connectionSuccess);
						Wizard.connectionTested = true;
						Wizard.enableNextButton();
						Wizard.clearConnectionAdvanceTimer();
						Wizard.connectionAdvanceTimer = setTimeout(function() {
							if (Wizard.currentStep === 2 && Wizard.connectionTested) {
								Wizard.nextStep();
							}
						}, 1500);
					} else {
						$result.addClass('error')
							.find('.test-result-icon').text('×');
						$result.find('.test-result-message').text(response.data || superdraftWizard.i18n.connectionError);
						Wizard.connectionTested = false;
						Wizard.enableNextButton();
					}
					$result.show();
				},
				error: function() {
					$result.addClass('error')
						.find('.test-result-icon').text('×');
					$result.find('.test-result-message').text(superdraftWizard.i18n.connectionError);
					$result.show();
					Wizard.connectionTested = false;
					Wizard.enableNextButton();
				},
				complete: function() {
					$btn.prop('disabled', false).text(originalText);
					Wizard.enableNextButton();
				}
			});
		},

		saveSettings: function() {
			// Save API key and feature-specific model defaults.
			$.ajax({
				url: superdraftWizard.ajax_url,
				type: 'POST',
				data: {
					action: 'superdraft_wizard_save_api',
					nonce: superdraftWizard.nonce,
					provider: this.selectedProvider,
					api_key: this.apiKey
				},
				success: function(response) {
					if (response.success) {
						// Save enabled modules.
						Wizard.saveModules();
					}
				}
			});
		},

		saveModules: function() {
			$.ajax({
				url: superdraftWizard.ajax_url,
				type: 'POST',
				data: {
					action: 'superdraft_wizard_save_modules',
					nonce: superdraftWizard.nonce,
					modules: this.enabledModules
				},
				success: function(response) {
					if (response.success) {
						// Modules saved.
					}
				}
			});
		},

		createDemoPost: function() {
			var $btn = $('.superdraft-wizard-create-demo');
			$btn.prop('disabled', true).text(superdraftWizard.i18n.creatingDemo || 'Creating...');

			$.ajax({
				url: superdraftWizard.ajax_url,
				type: 'POST',
				data: {
					action: 'superdraft_wizard_create_demo',
					nonce: superdraftWizard.nonce
				},
				success: function(response) {
					if (response.success && response.data.edit_url) {
						$btn.text(superdraftWizard.i18n.demoCreated || 'Opening editor...');
						window.location.href = response.data.edit_url;
					} else {
						alert('Failed to create demo post.');
						$btn.prop('disabled', false).text('Create a Demo Post');
					}
				},
				error: function() {
					alert('Failed to create demo post.');
					$btn.prop('disabled', false).text('Create a Demo Post');
				}
			});
		},

		dismissWizard: function() {
			$.ajax({
				url: superdraftWizard.ajax_url,
				type: 'POST',
				data: {
					action: 'superdraft_wizard_dismiss',
					nonce: superdraftWizard.nonce
				},
				success: function(response) {
					if (response.success) {
						window.location.href = superdraftWizard.ajax_url.replace('admin-ajax.php', 'admin.php?page=superdraft-settings');
					}
				}
			});
		}
	};

	// Initialize when DOM is ready.
	$(function() {
		Wizard.init();
	});

})(jQuery);
