<?php

use FluentForm\App\Helpers\Helper;
use FluentForm\App\Modules\Component\Component;

/**
 * All registered action's handlers should be in app\Hooks\Handlers,
 * addAction is similar to add_action and addCustomAction is just a
 * wrapper over add_action which will add a prefix to the hook name
 * using the plugin slug to make it unique in all wordpress plugins,
 * ex: $app->addCustomAction('foo', ['FooHandler', 'handleFoo']) is
 * equivalent to add_action('slug-foo', ['FooHandler', 'handleFoo']).
 */

/**
 * @var $app FluentForm\Framework\Foundation\Application
 */

// From MenuProvider.php
$app->addAction(
    'admin_menu',
    function () use ($app) {
        (new \FluentForm\App\Modules\Registerer\Menu($app))->register();
    }
);

$app->addAction(
    'ff_fluentform_form_application_view_editor',
    function ($formId) use ($app) {
        (new \FluentForm\App\Modules\Registerer\Menu($app))->renderEditor($formId);
    }
);

$app->addAction(
    'ff_fluentform_form_application_view_settings',
    function ($formId) use ($app) {
        (new \FluentForm\App\Modules\Registerer\Menu($app))->renderSettings($formId);
    }
);

$app->addAction(
    'fluentform_form_settings_container_form_settings',
    function ($formId) use ($app) {
        (new \FluentForm\App\Modules\Registerer\Menu($app))->renderFormSettings($formId);
    }
);

$app->addAction(
    'fluentform_global_settings_component_settings',
    function () use ($app) {
        (new \FluentForm\App\Modules\Renderer\GlobalSettings\Settings($app))->render();
    }
);

$app->addAction(
    'fluentform_global_settings_component_reCaptcha',
    function () use ($app) {
        (new \FluentForm\App\Modules\Renderer\GlobalSettings\Settings($app))->render();
    }
);

$app->addAction(
    'fluentform_global_settings_component_hCaptcha',
    function () use ($app) {
        (new \FluentForm\App\Modules\Renderer\GlobalSettings\Settings($app))->render();
    }
);

// From Backend.php
add_action('admin_init', function () use ($app) {
    (new \FluentForm\App\Modules\Registerer\Menu($app))->reisterScripts();
}, 9);

add_action('admin_enqueue_scripts', function () use ($app) {
    (new \FluentForm\App\Modules\Registerer\Menu($app))->enqueuePageScripts();
}, 10);

// Add Entries Menu
$app->addAction('ff_fluentform_form_application_view_entries', function ($form_id) {
    (new \FluentForm\App\Modules\Entries\Entries())->renderEntries($form_id);
});

$app->addAction('fluentform_after_form_navigation', function ($form_id) use ($app) {
    (new \FluentForm\App\Modules\Registerer\Menu($app))->addCopyShortcodeButton($form_id);
    (new \FluentForm\App\Modules\Registerer\Menu($app))->addPreviewButton($form_id);
});

$app->addAction('media_buttons', function () {
    (new \FluentForm\App\Modules\EditorButtonModule())->addButton();
});

$app->addAction('fluentform_addons_page_render_fluentform_add_ons', function () {
    (new \FluentForm\App\Modules\AddOnModule())->showFluentAddOns();
});

// This is temp, we will remove this after 2-3 versions.
add_filter('pre_set_site_transient_update_plugins', function ($updates) {
    if (!empty($updates->response['fluentformpro'])) {
        $updates->response['fluentformpro/fluentformpro.php'] = $updates->response['fluentformpro'];
        unset($updates->response['fluentformpro']);
    }

    return $updates;
}, 999, 1);

$app->addAction('fluentform_global_menu', function () use ($app) {
    $menu = new \FluentForm\App\Modules\Registerer\Menu($app);
    $menu->renderGlobalMenu();
    if ('yes' != get_option('fluentform_scheduled_actions_migrated')) {
        \FluentForm\App\Databases\Migrations\ScheduledActions::migrate();
    }

    $hookName = 'fluentform_do_scheduled_tasks';
    if (!wp_next_scheduled($hookName)) {
        wp_schedule_event(time(), 'ff_every_five_minutes', $hookName);
    }

    $emailReportHookName = 'fluentform_do_email_report_scheduled_tasks';
    if (!wp_next_scheduled($emailReportHookName)) {
        wp_schedule_event(time(), 'daily', $emailReportHookName);
    }
});

$app->addAction('wp_dashboard_setup', function () {
    $roleManager = new \FluentForm\App\Modules\Acl\Acl();

    if (!$roleManager->getCurrentUserCapability()) {
        return;
    }
    wp_add_dashboard_widget('fluentform_stat_widget', __('Fluent Forms Latest Form Submissions', 'fluentform'), function () {
        (new \FluentForm\App\Modules\DashboardWidgetModule())->showStat();
    }, 10, 1);
});

add_action('admin_init', function () {
    $disablePages = [
        'fluent_forms',
        'fluent_forms_transfer',
        'fluent_forms_settings',
        'fluent_forms_add_ons',
        'fluent_forms_docs',
        'fluent_forms_all_entries',
        'msformentries',
    ];

    $page = wpFluentForm('request')->get('page');

    if ($page && in_array($page, $disablePages)) {
        remove_all_actions('admin_notices');
    }
});

add_action('enqueue_block_editor_assets', function () {
    wp_enqueue_script(
        'fluentform-gutenberg-block',
        fluentFormsMix('js/fluent_gutenblock.js'),
        ['wp-element', 'wp-polyfill', 'wp-i18n', 'wp-blocks', 'wp-components'],
        FLUENTFORM_VERSION
    );

    $forms = wpFluent()->table('fluentform_forms')
        ->select(['id', 'title'])
        ->orderBy('id', 'DESC')
        ->get();

    array_unshift($forms, (object) [
        'id'    => '',
        'title' => __('-- Select a form --', 'fluentform'),
    ]);

    wp_localize_script('fluentform-gutenberg-block', 'fluentform_block_vars', [
        'logo'  => fluentFormsMix('img/fluent_icon.png'),
        'forms' => $forms,
    ]);

    wp_enqueue_style(
        'fluentform-gutenberg-block',
        fluentFormsMix('css/fluent_gutenblock.css'),
        ['wp-edit-blocks']
    );
});

add_action('wp_print_scripts', function () {
    if (is_admin()) {
        if (\FluentForm\App\Helpers\Helper::isFluentAdminPage()) {
            $option = get_option('_fluentform_global_form_settings');
            $isSkip = 'no' == \FluentForm\Framework\Helpers\ArrayHelper::get($option, 'misc.noConflictStatus');
            $isSkip = apply_filters('fluentform_skip_no_conflict', $isSkip);

            if ($isSkip) {
                return;
            }

            global $wp_scripts;
            if (!$wp_scripts) {
                return;
            }

            $pluginUrl = plugins_url();
            foreach ($wp_scripts->queue as $script) {
                if (!isset($wp_scripts->registered[$script])) {
                    continue;
                }

                $src = $wp_scripts->registered[$script]->src;
                if (false !== strpos($src, $pluginUrl) && false !== !strpos($src, 'fluentform')) {
                    wp_dequeue_script($wp_scripts->registered[$script]->handle);
                }
            }
        }
    }
}, 1);

add_action('fluentform_loading_editor_assets', function ($form) {
    add_filter('fluentform_editor_init_element_input_name', function ($field) {
        if (empty($field['settings']['label_placement'])) {
            $field['settings']['label_placement'] = '';
        }

        return $field;
    });

    $upgradableCheckInputs = [
        'input_radio',
        'select',
        'select_country',
        'input_checkbox',
    ];

    foreach ($upgradableCheckInputs as $upgradeElement) {
        add_filter('fluentform_editor_init_element_' . $upgradeElement, function ($element) use ($upgradeElement) {
            if (!\FluentForm\Framework\Helpers\ArrayHelper::get($element, 'settings.advanced_options')) {
                $formattedOptions = [];
                $oldOptions = \FluentForm\Framework\Helpers\ArrayHelper::get($element, 'options', []);
                foreach ($oldOptions as $value => $label) {
                    $formattedOptions[] = [
                        'label'      => $label,
                        'value'      => $value,
                        'calc_value' => '',
                        'image'      => '',
                    ];
                }
                $element['settings']['advanced_options'] = $formattedOptions;
                $element['settings']['enable_image_input'] = false;
                $element['settings']['calc_value_status'] = false;
                unset($element['options']);

                if ('input_radio' == $upgradeElement || 'input_checkbox' == $upgradeElement) {
                    $element['editor_options']['template'] = 'inputCheckable';
                }
            }

            if (!isset($element['settings']['layout_class']) && in_array($upgradeElement, ['input_radio', 'input_checkbox'])) {
                $element['settings']['layout_class'] = '';
            }

            if (!isset($element['settings']['dynamic_default_value'])) {
                $element['settings']['dynamic_default_value'] = '';
            }

            if ('select_country' != $upgradeElement && !isset($element['settings']['randomize_options'])) {
                $element['settings']['randomize_options'] = 'no';
            }

            if ('select' == $upgradeElement && \FluentForm\Framework\Helpers\ArrayHelper::get($element, 'attributes.multiple')) {
                if (empty($element['settings']['max_selection'])) {
                    $element['settings']['max_selection'] = '';
                }
                if (isset($element['settings']['enable_select_2'])) {
                    \FluentForm\Framework\Helpers\ArrayHelper::forget($element, 'settings.enable_select_2');
                }
            }

            if (
                (
                    (
                        'select' == $upgradeElement &&
                        !\FluentForm\Framework\Helpers\ArrayHelper::get($element, 'attributes.multiple')
                    ) ||
                    'select_country' == $upgradeElement
                ) &&
                !isset($element['settings']['enable_select_2'])
            ) {
                $element['settings']['enable_select_2'] = 'no';
            }

            if ('select_country' != $upgradeElement && !isset($element['settings']['values_visible'])) {
                $element['settings']['values_visible'] = false;
            }

            return $element;
        });
    }

    $upgradableFileInputs = [
        'input_file',
        'input_image',
    ];
    foreach ($upgradableFileInputs as $upgradeElement) {
        add_filter('fluentform_editor_init_element_' . $upgradeElement, function ($element) {
            if (!isset($element['settings']['upload_file_location'])) {
                $element['settings']['upload_file_location'] = 'default';
            }
            if (!isset($element['settings']['file_location_type'])) {
                $element['settings']['file_location_type'] = 'follow_global_settings';
            }

            return $element;
        });
    }

    add_filter('fluentform_editor_init_element_gdpr_agreement', function ($element) {
        if (!isset($element['settings']['required_field_message'])) {
            $element['settings']['required_field_message'] = '';
        }

        return $element;
    });

    add_filter('fluentform_editor_init_element_input_text', function ($element) {
        if (!isset($element['attributes']['maxlength'])) {
            $element['attributes']['maxlength'] = '';
        }

        return $element;
    });

    add_filter('fluentform_editor_init_element_textarea', function ($element) {
        if (!isset($element['attributes']['maxlength'])) {
            $element['attributes']['maxlength'] = '';
        }

        return $element;
    });

    add_filter('fluentform_editor_init_element_input_date', function ($item) {
        if (!isset($item['settings']['date_config'])) {
            $item['settings']['date_config'] = '';
        }

        return $item;
    });

    add_filter('fluentform_editor_init_element_container', function ($item) {
        if (!isset($item['settings']['conditional_logics'])) {
            $item['settings']['conditional_logics'] = [];
        }

        if (!isset($item['settings']['container_width'])) {
            $item['settings']['container_width'] = '';
        }

        $shouldSetWidth = !empty($item['columns']) && (!isset($item['columns'][0]['width']) || !$item['columns'][0]['width']);

        if ($shouldSetWidth) {
            $perColumn = round(100 / count($item['columns']), 2);

            foreach ($item['columns'] as &$column) {
                $column['width'] = $perColumn;
            }
        }

        return $item;
    });

    add_filter('fluentform_editor_init_element_input_number', function ($item) {
        if (!isset($item['settings']['number_step'])) {
            $item['settings']['number_step'] = '';
        }
        if (!isset($item['settings']['numeric_formatter'])) {
            $item['settings']['numeric_formatter'] = '';
        }
        if (!isset($item['settings']['prefix_label'])) {
            $item['settings']['prefix_label'] = '';
        }
        if (!isset($item['settings']['suffix_label'])) {
            $item['settings']['suffix_label'] = '';
        }

        return $item;
    });

    add_filter('fluentform_editor_init_element_input_email', function ($item) {
        if (!isset($item['settings']['is_unique'])) {
            $item['settings']['is_unique'] = 'no';
        }
        if (!isset($item['settings']['unique_validation_message'])) {
            $item['settings']['unique_validation_message'] = __('Email address need to be unique.', 'fluentform');
        }
        if (!isset($item['settings']['prefix_label'])) {
            $item['settings']['prefix_label'] = '';
        }
        if (!isset($item['settings']['suffix_label'])) {
            $item['settings']['suffix_label'] = '';
        }

        return $item;
    });

    add_filter('fluentform_editor_init_element_input_text', function ($item) {
        if (isset($item['attributes']['data-mask'])) {
            if (!isset($item['settings']['data-mask-reverse'])) {
                $item['settings']['data-mask-reverse'] = 'no';
            }
            if (!isset($item['settings']['data-clear-if-not-match'])) {
                $item['settings']['data-clear-if-not-match'] = 'no';
            }
        } else {
            if (!isset($item['settings']['is_unique'])) {
                $item['settings']['is_unique'] = 'no';
            }
            if (!isset($item['settings']['unique_validation_message'])) {
                $item['settings']['unique_validation_message'] = __('This field value need to be unique.', 'fluentform');
            }
        }

        if (!isset($item['settings']['prefix_label'])) {
            $item['settings']['prefix_label'] = '';
        }
        if (!isset($item['settings']['suffix_label'])) {
            $item['settings']['suffix_label'] = '';
        }

        return $item;
    });

    add_filter('fluentform_editor_init_element_recaptcha', function ($item, $form) {
        $item['attributes']['name'] = 'g-recaptcha-response';

        return $item;
    }, 10, 2);

    add_filter('fluentform_editor_init_element_hcaptcha', function ($item, $form) {
        $item['attributes']['name'] = 'h-captcha-response';

        return $item;
    }, 10, 2);

    add_filter('fluentform_editor_init_element_turnstile', function ($item, $form) {
        $item['attributes']['name'] = 'cf-turnstile-response';

        return $item;
    }, 10, 2);
}, 10);

add_filter('fluentform_addons_extra_menu', function ($menus) {
    $menus['fluentform_pdf'] = __('Fluent Forms PDF', 'fluentform');

    return $menus;
}, 99, 1);

add_action('fluentform_addons_page_render_fluentform_pdf', function () use ($app) {
    $url = '';
    if (!defined('FLUENTFORM_PDF_VERSION')) {
        $url = wp_nonce_url(
            self_admin_url('update.php?action=install-plugin&plugin=fluentforms-pdf'),
            'install-plugin_fluentforms-pdf'
        );
    }

    $app->view->render('admin.addons.pdf_promo', [
        'install_url'  => $url,
        'is_installed' => defined('FLUENTFORM_PDF_VERSION'),
    ]);
});

//Add file upload location in global settings
add_filter('fluentform_get_global_settings_values', function ($values, $key) {
    if (is_array($key)) {
        if (in_array('_fluentform_global_form_settings', $key)) {
            $values['file_upload_optoins'] = FluentForm\App\Helpers\Helper::fileUploadLocations();
        }

        if (in_array('_fluentform_turnstile_details', $key)) {
            $values = FluentForm\App\Modules\Turnstile\Turnstile::ensureSettings($values);
        }
    }

    return $values;
}, 10, 2);

add_action('ff_installed_by', function ($by) {
    if (is_string($by) && !get_option('_ff_ins_by')) {
        update_option('_ff_ins_by', sanitize_text_field($by), 'no');
    }
});

//Enables recaptcha validation when autoload recaptcha enabled for all forms
$autoIncludeRecaptcha = [
    [
        'type'        => 'hcaptcha',
        'is_disabled' => !get_option('_fluentform_hCaptcha_keys_status', false),
    ],
    [
        'type'        => 'recaptcha',
        'is_disabled' => !get_option('_fluentform_reCaptcha_keys_status', false),
    ],
    [
        'type'        => 'turnstile',
        'is_disabled' => !get_option('_fluentform_turnstile_keys_status', false),
    ],
];

foreach ($autoIncludeRecaptcha as $input) {
    if ($input['is_disabled']) {
        continue;
    }
    add_filter('ff_has_auto_' . $input['type'], function () use ($input) {
        $option = get_option('_fluentform_global_form_settings');
        $autoload = \FluentForm\Framework\Helpers\ArrayHelper::get($option, 'misc.autoload_captcha');
        $type = \FluentForm\Framework\Helpers\ArrayHelper::get($option, 'misc.captcha_type');

        if ($autoload && $type == $input['type']) {
            return true;
        }

        return false;
    });
}

// from Frontend.php
if (defined('WP_ROCKET_VERSION')) {
    add_filter('rocket_excluded_inline_js_content', function ($lines) {
        $lines[] = 'fluent_form_ff_form_instance';
        $lines[] = 'fluentFormVars';
        $lines[] = 'fluentform_payment';

        return $lines;
    });
}
/*
 * Push captcha in all forms when enabled from global settings
 */
add_filter('fluentform_rendering_form', function ($form) {
    $option = get_option('_fluentform_global_form_settings');
    $enabled = \FluentForm\Framework\Helpers\ArrayHelper::get($option, 'misc.autoload_captcha');
    if (!$enabled) {
        return $form;
    }
    $type = \FluentForm\Framework\Helpers\ArrayHelper::get($option, 'misc.captcha_type');
    $reCaptcha = [
        'element'    => 'recaptcha',
        'attributes' => [
            'name' => 'recaptcha',
        ],
    ];
    $hCaptcha = [
        'element'    => 'hcaptcha',
        'attributes' => [
            'name' => 'hcaptcha',
        ],
    ];
    $turnstile = [
        'element'    => 'turnstile',
        'attributes' => [
            'name' => 'turnstile',
        ],
    ];

    if ('recaptcha' == $type) {
        $captcha = $reCaptcha;
    } elseif ('hcaptcha' == $type) {
        $captcha = $hCaptcha;
    } elseif ('turnstile' == $type) {
        $captcha = $turnstile;
    }

    // place recaptcha below custom submit button
    $hasCustomSubmit = false;
    foreach ($form->fields['fields'] as $index => $field) {
        if ('custom_submit_button' == $field['element']) {
            $hasCustomSubmit = true;
            array_splice($form->fields['fields'], $index, 0, [$captcha]);
            break;
        }
    }
    if (!$hasCustomSubmit) {
        $form->fields['fields'][] = $captcha;
    }

    return $form;
}, 10, 1);

// from Common.php
add_action('save_post', function ($post_id) use ($app) {
    $post_content = $app->request->get('post_content');
    if ($post_content) {
        $post_content = wp_kses_post(wp_unslash($post_content));
    } else {
        $post = get_post($post_id);
        $post_content = $post->post_content;
    }

    $shortcodeIds = Helper::getShortCodeIds(
        $post_content,
        'fluentform',
        'id'
    );

    $shortcodeModalIds = Helper::getShortCodeIds(
        $post_content,
        'fluentform_modal',
        'form_id'
    );

    if ($shortcodeModalIds) {
        $shortcodeIds = array_merge($shortcodeIds, $shortcodeModalIds);
    }

    if ($shortcodeIds) {
        update_post_meta($post_id, '_has_fluentform', $shortcodeIds);
    } elseif (get_post_meta($post_id, '_has_fluentform', true)) {
        update_post_meta($post_id, '_has_fluentform', []);
    }
});

$component = new Component($app);
$component->addRendererActions();
$component->addFluentFormShortCode();
$component->addFluentFormDefaultValueParser();

$component->addFluentformSubmissionInsertedFilter();
$component->addIsRenderableFilter();
$component->registerInputSanitizers();

add_action('wp', function () use ($app) {
    // @todo: We will remove the fluentform_pages check from April 2021
    $fluentFormPages = $app->request->get('fluent_forms_pages') || $app->request->get('fluentform_pages');

    if ($fluentFormPages) {
        add_action('wp_enqueue_scripts', function () use ($app) {
            wp_enqueue_script('jquery');
            wp_enqueue_script(
                'fluent_forms_global',
                fluentFormsMix('js/fluent_forms_global.js'),
                ['jquery'],
                FLUENTFORM_VERSION,
                true
            );
            wp_localize_script('fluent_forms_global', 'fluent_forms_global_var', [
                'fluent_forms_admin_nonce' => wp_create_nonce('fluent_forms_admin_nonce'),
                'ajaxurl'                  => admin_url('admin-ajax.php'),
            ]);
            wp_enqueue_style('fluent-form-styles');
            $form = wpFluent()->table('fluentform_forms')->find(intval($app->request->get('preview_id')));
            if (apply_filters('fluentform_load_default_public', true, $form)) {
                wp_enqueue_style('fluentform-public-default');
            }
            wp_enqueue_script('fluent-form-submission');
            wp_enqueue_style('fluent-form-preview', fluentFormsMix('css/preview.css'));
        });

        (new \FluentForm\App\Modules\ProcessExteriorModule())->handleExteriorPages();
    }
}, 1);

$elements = [
    'select',
    'input_checkbox',
    'input_radio',
    'address',
    'select_country',
    'gdpr_agreement',
    'terms_and_condition',
];

foreach ($elements as $element) {
    $event = 'fluentform_response_render_' . $element;
    $app->addFilter($event, function ($response, $field, $form_id, $isLabel = false) {
        $element = $field['element'];

        if ('address' == $element && !empty($response->country)) {
            $countryList = getFluentFormCountryList();
            if (isset($countryList[$response->country])) {
                $response->country = $countryList[$response->country];
            }
        }

        if ('select_country' == $element) {
            $countryList = getFluentFormCountryList();
            if (isset($countryList[$response])) {
                $response = $countryList[$response];
            }
        }

        if (in_array($field['element'], ['gdpr_agreement', 'terms_and_condition'])) {
            if (!empty($response) && 'on' == $response) {
                $response = __('Accepted', 'fluentform');
            } else {
                $response = __('Declined', 'fluentform');
            }
        }

        if ($response && $isLabel && in_array($element, ['select', 'input_radio']) && !is_array($response)) {
            if (!isset($field['options'])) {
                $field['options'] = [];
                foreach (\FluentForm\Framework\Helpers\ArrayHelper::get($field, 'raw.settings.advanced_options', []) as $option) {
                    $field['options'][$option['value']] = $option['label'];
                }
            }
            if (isset($field['options'][$response])) {
                return $field['options'][$response];
            }
        }

        if (in_array($element, ['select', 'input_checkbox']) && is_array($response)) {
            return \FluentForm\App\Modules\Form\FormDataParser::formatCheckBoxValues($response, $field, $isLabel);
        }

        return \FluentForm\App\Modules\Form\FormDataParser::formatValue($response);
    }, 10, 4);
}

$app->addFilter('fluentform_response_render_textarea', function ($value, $field, $formId, $isHtml) {
    $value = $value ? nl2br($value) : $value;

    if (!$isHtml || !$value) {
        return $value;
    }

    return '<span style="white-space: pre-line">' . $value . '</span>';
}, 10, 4);

$app->addFilter('fluentform_response_render_input_file', function ($response, $field, $form_id, $isHtml = false) {
    return \FluentForm\App\Modules\Form\FormDataParser::formatFileValues($response, $isHtml, $form_id);
}, 10, 4);

$app->addFilter('fluentform_response_render_input_image', function ($response, $field, $form_id, $isHtml = false) {
    return \FluentForm\App\Modules\Form\FormDataParser::formatImageValues($response, $isHtml, $form_id);
}, 10, 4);

$app->addFilter('fluentform_response_render_input_repeat', function ($response, $field, $form_id) {
    return \FluentForm\App\Modules\Form\FormDataParser::formatRepeatFieldValue($response, $field, $form_id);
}, 10, 3);

$app->addFilter('fluentform_response_render_tabular_grid', function ($response, $field, $form_id, $isHtml = false) {
    return \FluentForm\App\Modules\Form\FormDataParser::formatTabularGridFieldValue($response, $field, $form_id, $isHtml);
}, 10, 4);

$app->addFilter('fluentform_response_render_input_name', function ($response) {
    return \FluentForm\App\Modules\Form\FormDataParser::formatName($response);
}, 10, 1);

$app->addFilter('fluentform_response_render_input_password', function ($value, $field, $formId) {
    if (Helper::shouldHidePassword($formId)) {
        $value = str_repeat('*', 6) . ' ' . __('(truncated)', 'fluentform');
    }

    return $value;
}, 10, 3);

$app->addFilter('fluentform_filter_insert_data', function ($data) {
    $settings = get_option('_fluentform_global_form_settings', false);
    if (is_array($settings) && isset($settings['misc'])) {
        if (isset($settings['misc']['isIpLogingDisabled'])) {
            if ($settings['misc']['isIpLogingDisabled']) {
                unset($data['ip']);
            }
        }
    }

    return $data;
});

// Register api response log hooks
$app->addAction(
    'fluentform_after_submission_api_response_success',
    function ($form, $entryId, $data, $feed, $res, $msg = '') {
        fluentform_after_submission_api_response_success($form, $entryId, $data, $feed, $res, $msg = '');
    },
    10,
    6
);

$app->addAction(
    'fluentform_after_submission_api_response_failed',
    function ($form, $entryId, $data, $feed, $res, $msg = '') {
        fluentform_after_submission_api_response_failed($form, $entryId, $data, $feed, $res, $msg = '');
    },
    10,
    6
);

function fluentform_after_submission_api_response_success($form, $entryId, $data, $feed, $res, $msg = '')
{
    try {
        $isDev = 'production' != wpFluentForm()->getEnv();
        if (!apply_filters('fluentform_api_success_log', $isDev, $form, $feed)) {
            return;
        }

        wpFluent()->table('fluentform_submission_meta')->insert([
            'response_id' => $entryId,
            'form_id'     => $form->id,
            'meta_key'    => 'api_log',
            'value'       => $msg,
            'name'        => $feed->formattedValue['name'],
            'status'      => 'success',
            'created_at'  => current_time('mysql'),
            'updated_at'  => current_time('mysql'),
        ]);
    } catch (Exception $e) {
        error_log($e->getMessage());
    }
}

function fluentform_after_submission_api_response_failed($form, $entryId, $data, $feed, $res, $msg = '')
{
    try {
        $isDev = 'production' != wpFluentForm()->getEnv();
        if (!apply_filters('fluentform_api_failed_log', $isDev, $form, $feed)) {
            return;
        }

        wpFluent()->table('fluentform_submission_meta')->insert([
            'response_id' => $entryId,
            'form_id'     => $form->id,
            'meta_key'    => 'api_log',
            'value'       => json_encode($res),
            'name'        => $feed->formattedValue['name'],
            'status'      => 'failed',
            'created_at'  => current_time('mysql'),
            'updated_at'  => current_time('mysql'),
        ]);
    } catch (Exception $e) {
        error_log($e->getMessage());
    }
}

$app->bind(
    'fluentFormAsyncRequest',
    new \FluentForm\App\Services\WPAsync\FluentFormAsyncRequest($app),
);

$app->addFilter('fluentform-disabled_analytics', function ($status) {
    $settings = get_option('_fluentform_global_form_settings');
    if (isset($settings['misc']['isAnalyticsDisabled']) && $settings['misc']['isAnalyticsDisabled']) {
        return true;
    }

    return $status;
});

$app->addAction('fluentform_before_form_render', function ($form) {
    do_action('fluentform_load_form_assets', $form->id);
});

$app->addAction('fluentform_load_form_assets', function ($formId) {
    // check if alreaded loaded
    if (!in_array($formId, \FluentForm\App\Helpers\Helper::$loadedForms)) {
        (new \FluentForm\App\Modules\Form\Settings\FormCssJs())->addCssJs($formId);
        \FluentForm\App\Helpers\Helper::$loadedForms[] = $formId;
        $selectedStyle = \FluentForm\App\Helpers\Helper::getFormMeta($formId, '_ff_selected_style');

        if ($selectedStyle) {
            do_action('fluentform_init_custom_stylesheet', $selectedStyle, $formId);
        }
    }
});

$app->addAction('fluentform_submission_inserted', function ($insertId, $formData, $form) use ($app) {
    $notificationManager = new \FluentForm\App\Services\Integrations\GlobalNotificationManager($app);
    $notificationManager->globalNotify($insertId, $formData, $form);
}, 10, 3);

$app->addAction('init', function () use ($app) {
    new \FluentForm\App\Services\Integrations\MailChimp\MailChimpIntegration($app);
});

$app->addAction('fluentform_form_element_start', function ($form) use ($app) {
    $honeyPot = new \FluentForm\App\Modules\Form\HoneyPot($app);
    $honeyPot->renderHoneyPot($form);
});

$app->addAction('fluentform_before_insert_submission', function ($insertData, $requestData, $form) use ($app) {
    $honeyPot = new \FluentForm\App\Modules\Form\HoneyPot($app);
    $honeyPot->verify($insertData, $requestData, $form->id);
}, 9, 3);

add_action('ff_log_data', function ($data) use ($app) {
    $dataLogger = new \FluentForm\App\Modules\Logger\DataLogger($app);
    $dataLogger->log($data);
});

// permision based filters
add_filter('fluentform_permission_callback', function ($status, $permission) {
    return (new \FluentForm\App\Modules\Acl\RoleManager())->currentUserFormFormCapability();
}, 10, 2);

// widgets
add_action('widgets_init', function () {
    register_widget('FluentForm\App\Modules\Widgets\SidebarWidgets');
});

add_action('wp', function () {
    global $post;

    if (!is_a($post, 'WP_Post')) {
        return;
    }

    $fluentFormIds = get_post_meta($post->ID, '_has_fluentform', true);

    if ($fluentFormIds && is_array($fluentFormIds)) {
        foreach ($fluentFormIds as $formId) {
            do_action('fluentform_load_form_assets', $formId);
        }
    }
});

add_filter('fluentform_validate_input_item_input_email', ['\FluentForm\App\Helpers\Helper', 'isUniqueValidation'], 10, 5);
add_filter('fluentform_validate_input_item_input_text', ['\FluentForm\App\Helpers\Helper', 'isUniqueValidation'], 10, 5);

add_filter('cron_schedules', function ($schedules) {
    $schedules['ff_every_five_minutes'] = [
        'interval' => 300,
        'display'  => esc_html__('Every 5 minutes (FluentForm)', 'fluentform'),
    ];

    return $schedules;
}, 10, 1);

add_action('fluentform_do_scheduled_tasks', 'fluentFormHandleScheduledTasks');
add_action('fluentform_do_email_report_scheduled_tasks', 'fluentFormHandleScheduledEmailReport');

add_action('ff_integration_action_result', function ($feed, $status, $note = '') {
    if (!isset($feed['scheduled_action_id']) || !$status) {
        return;
    }
    if (!$note) {
        $note = $status;
    }

    if (strlen($note) > 255) {
        if (function_exists('mb_substr')) {
            $note = mb_substr($note, 0, 251) . '...';
        } else {
            $note = substr($note, 0, 251) . '...';
        }
    }

    $actionId = intval($feed['scheduled_action_id']);
    wpFluent()->table('ff_scheduled_actions')
        ->where('id', $actionId)
        ->update([
            'status' => $status,
            'note'   => $note,
        ]);
}, 10, 3);

add_action('fluentform_global_notify_completed', function ($insertId, $form) use ($app) {
    if (strpos($form->form_fields, '"element":"input_password"') && apply_filters('fluentform_truncate_password_values', true, $form->id)) {
        // we have password
        (new \FluentForm\App\Services\Integrations\GlobalNotificationManager($app))->cleanUpPassword($insertId, $form);
    }
}, 10, 2);

/*
 * Elementor Block Init
 */

if (defined('ELEMENTOR_VERSION')) {
    new \FluentForm\App\Modules\Widgets\ElementorWidget($app);
}
/*
 * Oxygen Widget Init
 */

add_action('init', function () {
    if (class_exists('OxyEl')) {
        if (file_exists(FLUENTFORM_DIR_PATH . 'app/Modules/Widgets/OxygenWidget.php')) {
            new FluentForm\App\Modules\Widgets\OxygenWidget();
        }
    }
});

(new FluentForm\App\Services\Integrations\Slack\SlackNotificationActions($app))->register();

/*
 * Smartcode parser shortcodes
 */

add_filter('ff_will_return_html', function ($result, $integration, $key) {
    $dictionary = [
        'notifications' => ['message'],
    ];

    if (!isset($dictionary[$integration])) {
        return $result;
    }

    if (in_array($key, $dictionary[$integration])) {
        return true;
    }

    return $result;
}, 10, 3);

$app->addFilter('fluentform_response_render_input_number', function ($response, $field, $form_id, $isHtml = false) {
    if (!$response || !$isHtml) {
        return $response;
    }
    $fieldSettings = \FluentForm\Framework\Helpers\ArrayHelper::get($field, 'raw.settings');
    $formatter = \FluentForm\Framework\Helpers\ArrayHelper::get($fieldSettings, 'numeric_formatter');
    if (!$formatter) {
        return $response;
    }

    return \FluentForm\App\Helpers\Helper::getNumericFormatted($response, $formatter);
}, 10, 4);

new \FluentForm\App\Services\FormBuilder\Components\CustomSubmitButton();

if (function_exists('register_block_type')) {
    register_block_type('fluentfom/guten-block', [
        'render_callback' => function ($atts) {
            if (empty($atts['formId'])) {
                return '';
            }

            $className = \FluentForm\Framework\Helpers\ArrayHelper::get($atts, 'className');

            if ($className) {
                $classes = explode(' ', $className);
                $className = '';
                if (!empty($classes)) {
                    foreach ($classes as $class) {
                        $className .= sanitize_html_class($class) . ' ';
                    }
                }
            }
            $type = \FluentForm\App\Helpers\Helper::isConversionForm($atts['formId']) ? 'conversational' : '';

            return do_shortcode('[fluentform css_classes="' . $className . ' ff_guten_block" id="' . $atts['formId'] . '"  type="' . $type . '"]');
        },
        'attributes' => [
            'formId' => [
                'type' => 'string',
            ],
            'className' => [
                'type' => 'string',
            ],
        ],
    ]);
}

// require the CLI
if (defined('WP_CLI') && WP_CLI) {
    \WP_CLI::add_command('fluentform', '\FluentForm\App\Modules\CLI\Commands');
}
