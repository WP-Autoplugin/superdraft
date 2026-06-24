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
		selectedModel: '',
		connectionTested: false,
		enabledModules: {},

		init: function() {
			this.bindEvents();
			this.updateProgressBar();
			this.initModules();
		},

		bindEvents: function() {
			// Provider selection.
			$(document).on('click', '.superdraft-wizard-provider-card', function(e) {
				$('.superdraft-wizard-provider-card').removeClass('selected');
				$(this).addClass('selected');
				Wizard.selectedProvider = $(this).data('provider');
				Wizard.updateKeyHint();
				Wizard.updateKeyLinks();
				Wizard.enableNextButton();
			});

			// API key input.
			$(document).on('input', '#superdraft-wizard-api-key', function() {
				Wizard.apiKey = $(this).val().trim();
				Wizard.connectionTested = false;
				Wizard.enableNextButton();
			});

			// Model selection.
			$(document).on('change', '#superdraft-wizard-model', function() {
				Wizard.selectedModel = $(this).val();
				Wizard.enableNextButton();
			});

			// Navigation buttons.
			$(document).on('click', '.superdraft-wizard-next', function(e) {
				e.preventDefault();
				Wizard.nextStep();
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
				var module = $(this).closest('.superdraft-wizard-module-card').data('module');
				Wizard.enabledModules[module] = $(this).is(':checked');
			});
		},

		initModules: function() {
			// Initialize default enabled state.
			$('.module-checkbox').each(function() {
				var module = $(this).closest('.superdraft-wizard-module-card').data('module');
				Wizard.enabledModules[module] = $(this).is(':checked');
			});
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
				this.populateModels();
			} else if (step === 3) {
				this.updateModuleModels();
			} else if (step === 4) {
				this.saveSettings();
				this.updateSummary();
			}
		},

		nextStep: function() {
			if (this.currentStep < this.totalSteps) {
				this.showStep(this.currentStep + 1);
			}
		},

		prevStep: function() {
			if (this.currentStep > 1) {
				this.showStep(this.currentStep - 1);
			}
		},

		getProviderName: function(provider) {
			var names = {
				'openai': 'OpenAI',
				'anthropic': 'Anthropic',
				'google': 'Google / Gemini',
				'xai': 'xAI',
				'custom': 'Custom'
			};
			return names[provider] || provider;
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

		enableNextButton: function() {
			var $btn = $('.superdraft-wizard-step-content[data-step="' + this.currentStep + '"] .superdraft-wizard-next');

			if (this.currentStep === 1) {
				$btn.prop('disabled', !this.selectedProvider);
			} else if (this.currentStep === 2) {
				$btn.prop('disabled', !this.apiKey || !this.connectionTested || !this.selectedModel);
			} else if (this.currentStep === 3) {
				// Module step - always enabled since we have defaults.
				$btn.prop('disabled', false);
			}
		},

		disableNextButton: function() {
			$('.superdraft-wizard-step-content[data-step="' + this.currentStep + '"] .superdraft-wizard-next').prop('disabled', true);
		},

		populateModels: function() {
			var $select = $('#superdraft-wizard-model');
			$select.empty();
			$select.append('<option value="">' + (superdraftWizard.i18n.selectModel || 'Select a model...') + '</option>');

			var providerModels = superdraftWizard.models[this.getProviderName(this.selectedProvider)];
			if (providerModels) {
				$.each(providerModels, function(key, label) {
					$select.append('<option value="' + key + '">' + label + '</option>');
				});
			}

			// Select recommended model.
			var recommended = '';
			if (typeof superdraftRecommendedModels !== 'undefined') {
				recommended = superdraftRecommendedModels[this.selectedProvider] || '';
			}
			if (recommended) {
				$select.val(recommended);
				this.selectedModel = recommended;
				this.enableNextButton();
			}
		},

		updateModuleModels: function() {
			// Update the model display in each module card.
			$('.superdraft-wizard-module-card').each(function() {
				var modelName = Wizard.selectedModel;
				if (superdraftWizard.models && superdraftWizard.models[Wizard.getProviderName(Wizard.selectedProvider)]) {
					modelName = superdraftWizard.models[Wizard.getProviderName(Wizard.selectedProvider)][Wizard.selectedModel] || Wizard.selectedModel;
				}
				$(this).find('.module-model').text(modelName);
			});
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

		testConnection: function() {
			var $btn = $('.superdraft-wizard-test-btn');
			var $result = $('.superdraft-wizard-test-result');

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
							.find('.test-result-icon').text('✅');
						$result.find('.test-result-message').text(response.data || superdraftWizard.i18n.connectionSuccess);
						Wizard.connectionTested = true;
						Wizard.enableNextButton();
					} else {
						$result.addClass('error')
							.find('.test-result-icon').text('❌');
						$result.find('.test-result-message').text(response.data || superdraftWizard.i18n.connectionError);
						Wizard.connectionTested = false;
						Wizard.disableNextButton();
					}
					$result.show();
				},
				error: function() {
					$result.addClass('error')
						.find('.test-result-icon').text('❌');
					$result.find('.test-result-message').text(superdraftWizard.i18n.connectionError);
					$result.show();
					Wizard.connectionTested = false;
					Wizard.disableNextButton();
				},
				complete: function() {
					$btn.prop('disabled', false).text('Test Connection');
				}
			});
		},

		saveSettings: function() {
			// Save API key and model.
			$.ajax({
				url: superdraftWizard.ajax_url,
				type: 'POST',
				data: {
					action: 'superdraft_wizard_save_api',
					nonce: superdraftWizard.nonce,
					provider: this.selectedProvider,
					api_key: this.apiKey,
					model: this.selectedModel
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
