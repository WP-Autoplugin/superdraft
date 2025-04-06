jQuery(document).ready(function($) {
    // Tab navigation
    function setCookie(name, value, days) {
        const date = new Date();
        date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
        document.cookie = name + "=" + value + ";expires=" + date.toUTCString() + ";path=/";
    }

    function getCookie(name) {
        const value = `; ${document.cookie}`;
        const parts = value.split(`; ${name}=`);
        if (parts.length === 2) return parts.pop().split(';').shift();
    }

    function switchToTab(tabId, pushHistory = true) {
        const $tab = $(`a[href="#${tabId}"]`);
        $('.nav-tab').removeClass('nav-tab-active');
        $tab.addClass('nav-tab-active');
        $('.tab-content').hide();
        $(`#${tabId}`).show();
        setCookie('superdraft_active_tab', tabId, 30);
        
        if (pushHistory) {
            history.pushState({ tabId: tabId }, '', `#${tabId}`);
        }
    }

    // Handle tab clicks
    $('.nav-tab').click(function(e) {
        e.preventDefault();
        const tabId = $(this).attr('href').substring(1);
        switchToTab(tabId, true);
    });

    // Initial tab setup
    const hash = window.location.hash.substring(1);
    const savedTab = getCookie('superdraft_active_tab');
    let initialTab = null;

    if (hash && $('.nav-tab[href="#' + hash + '"]').length) {
        initialTab = hash;
    } else if (savedTab && $('.nav-tab[href="#' + savedTab + '"]').length) {
        initialTab = savedTab;
    } else {
        // Get the first tab if none is selected
        initialTab = $('.nav-tab').first().attr('href').substring(1);
    }

    // Set initial state
    history.replaceState({ tabId: initialTab }, '', initialTab ? `#${initialTab}` : '');
    switchToTab(initialTab, false);

    // Handle browser back/forward
    $(window).on('popstate', function(event) {
        const state = event.originalEvent.state;
        if (state && state.tabId) {
            switchToTab(state.tabId, false);
        } else {
            // Handle the case when there's no state (initial page load)
            const firstTab = $('.nav-tab').first().attr('href').substring(1);
            switchToTab(firstTab, false);
        }
    });

    // Custom models
    let customModels = JSON.parse($('#superdraft_custom_models').val() || '[]');

    function updateCustomModelsList() {
        const $list = $('.custom-models-items').empty();
        customModels.forEach((model, index) => {
            const $item = $('<div class="custom-model-item">')
            .append(`<strong>${model.name}</strong>`)
            .append(`<details><summary>${superdraft.i18n.details}</summary><p><strong>${superdraft.i18n.url}:</strong> ${model.url}</p><p><strong>${superdraft.i18n.modelParameter}:</strong> ${model.modelParameter}</p><p><strong>${superdraft.i18n.apiKey}:</strong> ***${model.apiKey.substr(-3)}</p><p><strong>${superdraft.i18n.headers}:</strong> ${model.headers.join(', ')}</p></details>`)
            .append(`<button type="button" class="button remove-model" data-index="${index}">${superdraft.i18n.remove}</button>`);
            $list.append($item);
        });
        $('#superdraft_custom_models').val(JSON.stringify(customModels));
        
        // Also update all the custom optgroups to show the new custom models
        $('.superdraft-models').each(function() {
            const currentModel = $(this).val();
            const $optgroup = $(this).find('optgroup.superdraft-models-group-custom-models').empty();
            customModels.forEach((model, index) => {
                const $option = $('<option>').val(model.name).text(model.name);
                $optgroup.append($option);
            });
            // Restore the current model selection
            $(this).val(currentModel);
        });

    }

    $('#add-custom-model').on('click', function() {
        const name = $('#custom-model-name').val();
        const url = $('#custom-model-url').val();
        const modelParameter = $('#custom-model-parameter').val();
        const apiKey = $('#custom-model-api-key').val();
        const headers = $('#custom-model-headers').val();

        if (!name || !url || !apiKey) {
            alert(superdraft.i18n.fillOutFields);
            return;
        }

        const model = {
            name: name,
            url: url,
            modelParameter: modelParameter,
            apiKey: apiKey,
            headers: headers.split('\n').filter(h => h.trim())
        };

        $.ajax({
            url: ajaxurl,
            method: 'POST',
            data: {
                action: 'superdraft_add_model',
                model: model,
                nonce: superdraft.nonce
            },
            success: function(response) {
                if (response.success) {
                    customModels = response.data.models;
                    updateCustomModelsList();
                    $('#custom-model-name, #custom-model-url, #custom-model-parameter, #custom-model-api-key, #custom-model-headers').val('');
                } else {
                    alert(response.data.message || superdraft.i18n.errorSavingModel);
                }
            }
        });
    });

    $(document).on('click', '.remove-model', function() {
        const index = $(this).data('index');
        if (confirm(superdraft.i18n.removeModel)) {
            $.ajax({
                url: ajaxurl,
                method: 'POST',
                data: {
                    action: 'superdraft_remove_model',
                    index: index,
                    nonce: superdraft.nonce
                },
                success: function(response) {
                    if (response.success) {
                        customModels = response.data.models;
                        updateCustomModelsList();
                    } else {
                        alert(response.data.message || superdraft.i18n.errorSavingModel);
                    }
                }
            });
        }
    });

    updateCustomModelsList();
});