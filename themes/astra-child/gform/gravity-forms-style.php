<?php
/**
 * Custom Component: Gravity Forms Modern UI & Transitions
 * Description: Styles Gravity Forms with a premium, Porsline/Typeform-style UI and enqueues high-end page-to-page transitions.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

// 1. Replace the default Gravity Forms AJAX spinner with a transparent pixel so we can style it via CSS
add_filter( 'gform_ajax_spinner_url', 'custom_gform_spinner_url', 10, 2 );
function custom_gform_spinner_url( $image_src, $form ) {
    return 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';
}

// 2. Output custom style in wp_head directly to ensure it works on all template pages
add_action( 'wp_head', 'output_custom_gform_styles', 999 );
function output_custom_gform_styles() {
    if ( ! class_exists( 'GFCommon' ) ) {
        return;
    }
    ?>
    <style id="custom-gform-styles">
    /* ==========================================================================
       Gravity Forms Modern UI & Styling
       ========================================================================== */
    
    /* Form Wrapper & Container */
    .porsline-form,
    .gform_wrapper {
        box-sizing: border-box !important;
        transition: height 0.4s cubic-bezier(0.25, 1, 0.5, 1) !important;

        @media (max-width: 640px) {
            padding: 24px 10px !important;
            margin: 0 auto !important;
            border-radius: 16px !important;
        }

        /* Elementor Popup & Container Height Fixes */
        display: block !important;
        height: auto;
        overflow: visible;

        /* Form Fields Base */
        /* .gfield {
            margin-bottom: 28px !important;
        } */
        
        /* Labels & Descriptions */
        .gfield_label {
            font-size: 15px !important;
            font-weight: 600 !important;
            color: #0f172a !important;
            margin-bottom: 8px !important;
            display: inline-block !important;
        }
        
        .gfield_description {
            font-size: 13px !important;
            color: #64748b !important;
            margin-top: 4px !important;
            margin-bottom: 12px !important;
            line-height: 1.6 !important;
        }
        .gform-body { 
            margin-top: 40px;
        }

        /* Input Fields (Text, Email, Tel, Url, Number, Select, Textarea) */
        input[type="text"],
        input[type="email"],
        input[type="tel"],
        input[type="url"],
        input[type="number"],
        select,
        textarea {
            width: 100% !important;
            padding: 12px 16px !important;
            border: 1.5px solid #cbd5e1 !important;
            border-radius: 10px !important;
            background-color: #ffffff !important;
            color: #0f172a !important;
            -webkit-text-fill-color: #0f172a !important;
            font-size: 15px !important;
            font-family: inherit !important;
            transition: all 0.2s ease !important;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.02) !important;
            box-sizing: border-box !important;

            &:focus {
                border-color: #2563eb !important;
                box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15) !important;
                outline: none !important;
                background-color: #ffffff !important;
            }
        }

        select {
            width: 85% !important;
            padding: 5px 10px !important;
            box-sizing: content-box !important;
            color: #0f172a !important;
            -webkit-text-fill-color: #0f172a !important;
            background-color: #ffffff !important;
        }

        /* Force dark text color on standard select options, select fields, and Choices.js enhancements */
        select option,
        .choices,
        .choices__inner,
        .choices__list,
        .choices__item,
        .choices__placeholder,
        .choices__list--single .choices__item,
        .choices__list--dropdown .choices__item--selectable,
        .choices__list--single .choices__item--selectable {
            color: #0f172a !important;
            -webkit-text-fill-color: #0f172a !important;
            background-color: #ffffff !important;
            box-sizing: border-box !important;
        }
        
        .choices__list--dropdown .choices__item--selectable.is-highlighted {
            background-color: #eff6ff !important;
            color: #1e40af !important;
            -webkit-text-fill-color: #1e40af !important;
        }

        /* Error Validation States */
        .gfield_error {
            input, select, textarea {
                border-color: #ef4444 !important;

                &:focus {
                    box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.15) !important;
                }
            }
        }
        
        .validation_message {
            color: #ef4444 !important;
            font-size: 13px !important;
            font-weight: 500 !important;
            margin-top: 6px !important;
        }

        .gform_validation_errors {
            background: #fef2f2 !important;
            border: 1px solid #fca5a5 !important;
            border-radius: 12px !important;
            color: #991b1b !important;
            padding: 16px !important;
            margin-bottom: 26px !important;
            font-size: 14px !important;
            box-shadow: none !important;
        }

        /* Modern Progress Bar */
        .gform_page_header {
            margin-bottom: 32px !important;
            border-bottom: 1px solid #f1f5f9 !important;
            padding-bottom: 20px !important;
        }

        .gf_progressbar {
            background-color: #f1f5f9 !important;
            border-radius: 100px !important;
            height: 8px !important;
            overflow: hidden !important;
            margin-top: 8px !important;
            box-shadow: none !important;
            display: flex !important;
            justify-content: flex-start !important;
        }

        /* Align progress bar percentage to the right for RTL layout */
        /* &[dir="rtl"] .gf_progressbar,
        body.rtl & .gf_progressbar,
        body.rtl .gf_progressbar {
            justify-content: flex-end !important;
        } */

        .gf_progressbar_percentage {
            background: linear-gradient(90deg, #2563eb, #4f46e5) !important;
            border-radius: 100px !important;
            height: 100% !important;
            transition: width 0.6s cubic-bezier(0.25, 1, 0.5, 1) !important;
            box-shadow: 0 0 8px rgba(37, 99, 235, 0.25) !important;
        }

        /* Reverse gradient flow for RTL layout */
        &[dir="rtl"] .gf_progressbar_percentage,
        body.rtl & .gf_progressbar_percentage,
        body.rtl .gf_progressbar_percentage {
            background: linear-gradient(270deg, #2563eb, #4f46e5) !important;
        }
        
        .gf_progressbar_title {
            font-size: 13px !important;
            font-weight: 600 !important;
            color: #475569 !important;
            margin-bottom: 0 !important;
        }

        /* Modern Card Layout for Radio Buttons & Checkboxes */
        .gfield_radio,
        .gfield_checkbox {
            display: flex !important;
            flex-direction: column !important;
            gap: 12px !important;
            margin-top: 6px !important;
            list-style: none !important;
            padding: 0 !important;

            .gchoice {
                position: relative !important;
                margin: 0 !important;
                padding: 0 !important;

                input[type="radio"],
                input[type="checkbox"] {
                    position: absolute !important;
                    opacity: 0 !important;
                    width: 0 !important;
                    height: 0 !important;
                    margin: 0 !important;
                    padding: 0 !important;
                    pointer-events: none !important;

                    &:checked + label {
                        background: #eff6ff !important;
                        border-color: #2563eb !important;
                        color: #1e40af !important;
                        font-weight: 600 !important;
                        box-shadow: 0 4px 12px rgba(37, 99, 235, 0.04) !important;
                    }

                    &:focus-visible + label {
                        outline: 2px solid #2563eb !important;
                        outline-offset: 2px !important;
                    }
                }

                label {
                    display: flex !important;
                    align-items: center !important;
                    width: 100% !important;
                    padding: 16px 20px !important;
                    background: #f8fafc !important;
                    border: 1.5px solid #e2e8f0 !important;
                    border-radius: 12px !important;
                    cursor: pointer !important;
                    font-size: 15px !important;
                    font-weight: 500 !important;
                    color: #334155 !important;
                    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1) !important;
                    user-select: none !important;
                    box-sizing: border-box !important;
                    max-width: 100% !important;
                    margin: 0 !important;
                    @media (max-width: 640px) {
                        font-size: 11px !important;
                        padding: 15px 8px !important;
                    }
                    &::before {
                        content: "" !important;
                        display: inline-block !important;
                        width: 18px !important;
                        height: 18px !important;
                        flex-shrink: 0 !important;
                        background: #ffffff !important;
                        border: 2px solid #cbd5e1 !important;
                        transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1) !important;
                    }
                }

                &:hover label {
                    background: #f1f5f9 !important;
                    border-color: #cbd5e1 !important;
                    color: #0f172a !important;
                }
            }
        }

        /* Radio Indicator: Circle */
        .gfield_radio label::before {
            border-radius: 50% !important;
        }

        /* Checkbox Indicator: Square */
        .gfield_checkbox label::before {
            border-radius: 4px !important;
        }

        /* Directional spacing for RTL/LTR */
        &:not([dir="rtl"]) .gfield_radio label::before,
        &:not([dir="rtl"]) .gfield_checkbox label::before {
            margin-right: 14px !important;
        }
        
        &[dir="rtl"] .gfield_radio label::before,
        &[dir="rtl"] .gfield_checkbox label::before,
        body.rtl & .gfield_radio label::before,
        body.rtl & .gfield_checkbox label::before {
            margin-left: 14px !important;
        }

        /* Radio Checked Indicator state */
        .gfield_radio input[type="radio"]:checked + label::before {
            border-color: #2563eb !important;
            background: #2563eb !important;
            box-shadow: inset 0 0 0 4px #eff6ff !important;
        }

        /* Checkbox Checked Indicator state (custom SVG checkmark icon) */
        .gfield_checkbox input[type="checkbox"]:checked + label::before {
            border-color: #2563eb !important;
            background: #2563eb url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='white' stroke-width='4' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='20 6 9 17 4 12'%3E%3C/polyline%3E%3C/svg%3E") no-repeat center !important;
            background-size: 11px !important;
        }

        /* Form Page Footer & Submit Buttons */
        .gform_page_footer,
        .gform_footer {
            display: flex !important;
            justify-content: space-between !important;
            align-items: center !important;
            margin-top: 36px !important;
            padding: 6px !important;
            border-radius: 15px !important;
            background: #e6e6e617;
            border: 1px solid #4949491f;
        }

        /* Flex-end if only one button (e.g. submit or first step) */
        /* .gform_footer,
        .gform_page_footer:not(:has(.gform_previous_button)) {
            justify-content: flex-end !important;
        } */

        .gform_page_footer input[type="button"],
        .gform_footer input[type="submit"],
        .gform_page_footer input[type="submit"] {
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            padding: 12px 28px !important;
            font-size: 15px !important;
            font-weight: 600 !important;
            border-radius: 10px !important;
            cursor: pointer !important;
            transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1) !important;
            font-family: inherit !important;
            box-sizing: border-box !important;
            height: auto !important;
            line-height: 1.5 !important;
            @media (max-width: 640px) {
                font-size: 12px !important;
                padding: 12px 20px !important;
            }
        }

        /* Next / Submit buttons */
        .gform_next_button {
            margin-right: auto !important;
        }
        .gform_next_button,
        .gform_button,
        input[type="submit"] {
            background: #2563eb !important;
            color: #ffffff !important;
            border: none !important;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.12) !important;

            &:hover {
                background: #1d4ed8 !important;
                transform: translateY(-1.5px) !important;
                box-shadow: 0 6px 16px rgba(37, 99, 235, 0.2) !important;
            }

            &:active {
                transform: translateY(0) !important;
            }
        }

        /* Previous buttons */
        .gform_previous_button {
            background: #f1f5f9 !important;
            color: #475569 !important;
            border: 1px solid #cbd5e1 !important;
            box-shadow: none !important;

            &:hover {
                background: #e2e8f0 !important;
                color: #0f172a !important;
                transform: translateY(-1.5px) !important;
            }

            &:active {
                transform: translateY(0) !important;
            }
        }

        /* Custom CSS Loading Spinner */
        .gform_ajax_spinner {
            display: inline-block !important;
            width: 18px !important;
            height: 18px !important;
            border: 2px solid #cbd5e1 !important;
            border-top: 2px solid #2563eb !important;
            border-radius: 50% !important;
            animation: gform-spin 0.8s linear infinite !important;
            margin: 0 8px !important;
            vertical-align: middle !important;
        }

        /* Smooth Page Transitions */
        .gform_page {
            transition: opacity 0.4s cubic-bezier(0.25, 1, 0.5, 1), transform 0.4s cubic-bezier(0.25, 1, 0.5, 1) !important;
            will-change: transform, opacity !important;
        }
    }

    @keyframes gform-spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }

    /* Page Exit & Entry Animations */
    .gform-slide-out-left {
        opacity: 0 !important;
        transform: translateX(-40px) !important;
    }

    .gform-slide-out-right {
        opacity: 0 !important;
        transform: translateX(40px) !important;
    }

    .gform-slide-in-right {
        animation: gformSlideInRight 0.45s cubic-bezier(0.25, 1, 0.5, 1) forwards !important;
    }

    .gform-slide-in-left {
        animation: gformSlideInLeft 0.45s cubic-bezier(0.25, 1, 0.5, 1) forwards !important;
    }

    .gform-slide-up {
        animation: gformSlideUp 0.5s cubic-bezier(0.25, 1, 0.5, 1) forwards !important;
    }

    @keyframes gformSlideInRight {
        from {
            opacity: 0;
            transform: translateX(40px);
        }
        to {
            opacity: 1;
            transform: translateX(0);
        }
    }

    @keyframes gformSlideInLeft {
        from {
            opacity: 0;
            transform: translateX(-40px);
        }
        to {
            opacity: 1;
            transform: translateX(0);
        }
    }

    @keyframes gformSlideUp {
        from {
            opacity: 0;
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    </style>
    <?php
}

// 3. Output custom JS script in wp_footer directly to guarantee initialization
add_action( 'wp_footer', 'output_custom_gform_scripts', 999 );
function output_custom_gform_scripts() {
    if ( ! class_exists( 'GFCommon' ) ) {
        return;
    }
    ?>
    <script id="custom-gform-transitions">
    (function($) {
        let direction = 'next';
        let initialLoad = true;
        let prevHeight = 0;

        // Helper to lock the form height before the content transitions
        function lockFormHeight(formWrapper) {
            prevHeight = formWrapper.outerHeight();
            formWrapper.css({
                'height': prevHeight + 'px',
                'overflow': 'hidden'
            });
        }

        // Listen for Next clicks
        $(document).on('click', '.gform_wrapper .gform_next_button, .gform_wrapper .gform_button, .gform_wrapper input[type="submit"]', function() {
            direction = 'next';
            const wrapper = $(this).closest('.gform_wrapper');
            lockFormHeight(wrapper);
            $(this).closest('.gform_page').addClass('gform-slide-out-left');
        });

        // Listen for Previous clicks
        $(document).on('click', '.gform_wrapper .gform_previous_button', function() {
            direction = 'previous';
            const wrapper = $(this).closest('.gform_wrapper');
            lockFormHeight(wrapper);
            $(this).closest('.gform_page').addClass('gform-slide-out-right');
        });

        // Handle page loads (initial and via AJAX)
        $(document).on('gform_post_render', function(event, formId, currentPage) {
            const formWrapper = $('#gform_wrapper_' + formId);
            if (!formWrapper.length) {
                return;
            }

            // Smooth Progress Bar Transition
            const progressPercentage = formWrapper.find('.gf_progressbar_percentage');
            if (progressPercentage.length) {
                // Get target width from GF's inline style
                const styleAttr = progressPercentage.attr('style') || '';
                const match = styleAttr.match(/width\s*:\s*([\d.]+)%/);
                const targetWidth = match ? parseFloat(match[1]) : 0;

                if (targetWidth > 0) {
                    const storageKey = 'gf_progress_' + formId;
                    const lastWidth = sessionStorage.getItem(storageKey);
                    
                    let startWidth = 0;
                    if (lastWidth !== null) {
                        startWidth = parseFloat(lastWidth);
                    }

                    // Instantly set to startWidth
                    progressPercentage.css({
                        'transition': 'none',
                        'width': startWidth + '%'
                    });

                    // Force a reflow
                    progressPercentage[0].offsetHeight;

                    // Animate to target width after a tiny delay
                    setTimeout(function() {
                        progressPercentage.css({
                            'transition': 'width 0.8s cubic-bezier(0.25, 1, 0.5, 1)',
                            'width': targetWidth + '%'
                        });
                    }, 50);

                    // Update persistent width
                    sessionStorage.setItem(storageKey, targetWidth);
                }
            } else {
                // If there's no progress bar on this page, maybe it's the confirmation page. Clean up.
                sessionStorage.removeItem('gf_progress_' + formId);
            }

            const formPage = formWrapper.find('.gform_page');
            if (!formPage.length) {
                formWrapper.css({
                    'height': 'auto',
                    'overflow': 'visible'
                });
                return;
            }

            // Smooth Height Resize Transition
            if (!initialLoad && prevHeight > 0) {
                // Wait 50ms for conditional logic hide/show animations to finish before measuring
                setTimeout(function() {
                    // Temporarily disable height transition to set start point, then measure destination
                    formWrapper.css({
                        'transition': 'none',
                        'height': prevHeight + 'px',
                        'overflow': 'hidden'
                    });
                    
                    // Force a reflow
                    formWrapper[0].offsetHeight;

                    // Temporarily reset height to auto to measure new height
                    formWrapper.css('height', 'auto');
                    const newHeight = formWrapper.outerHeight();

                    // Set back to previous height, force reflow, and animate to new height
                    formWrapper.css('height', prevHeight + 'px');
                    formWrapper[0].offsetHeight;
                    
                    formWrapper.css({
                        'transition': 'height 0.45s cubic-bezier(0.25, 1, 0.5, 1)',
                        'height': newHeight + 'px'
                    });

                    // Revert to auto height after transition finishes to maintain responsive layout & conditional logic expansion
                    setTimeout(function() {
                        formWrapper.css({
                            'height': 'auto',
                            'transition': '',
                            'overflow': 'visible'
                        });
                    }, 500);
                }, 60);
            } else {
                formWrapper.css({
                    'height': 'auto',
                    'overflow': 'visible'
                });
            }

            // Apply entry animations based on state
            if (initialLoad) {
                formPage.addClass('gform-slide-up');
                initialLoad = false;
            } else {
                if (direction === 'next') {
                    formPage.addClass('gform-slide-in-right');
                } else {
                    formPage.addClass('gform-slide-in-left');
                }
            }

            // Cleanup animation classes after they complete to keep the DOM clean
            setTimeout(function() {
                formPage.removeClass('gform-slide-in-right gform-slide-in-left gform-slide-up');
            }, 600);

            // Optional: Premium Auto-Advance behavior for Card options
            // If a radio container has class 'gf-auto-advance', click native Next on select
            formPage.find('.gf-auto-advance').find('input[type="radio"]').on('change', function() {
                const nextButton = formPage.find('.gform_next_button');
                if (nextButton.length) {
                    setTimeout(function() {
                        nextButton.click();
                    }, 350);
                }
            });
        });

        // Listen for Gravity Forms conditional logic events to unlock and adjust height dynamically
        $(document).on('gform_post_conditional_logic', function(event, formId, fields, isInit) {
            const formWrapper = $('#gform_wrapper_' + formId);
            if (formWrapper.length) {
                setTimeout(function() {
                    formWrapper.css({
                        'height': 'auto',
                        'overflow': 'visible'
                    });
                }, 50);
            }
        });

        // Listen for change events on radio/checkbox/select inputs to ensure wrapper expands smoothly
        $(document).on('change', '.gform_wrapper input, .gform_wrapper select', function() {
            const formWrapper = $(this).closest('.gform_wrapper');
            if (formWrapper.length) {
                setTimeout(function() {
                    formWrapper.css({
                        'height': 'auto',
                        'overflow': 'visible'
                    });
                }, 100);
            }
        });

        // Fix for Elementor Popups: Reset styles when popup opens
        $(document).on('elementor/popup/show', function() {
            $('.gform_wrapper').css({
                'height': 'auto',
                'transition': '',
                'overflow': 'visible',
                'display': 'block'
            });
        });
    })(jQuery);
    </script>
    <?php
}
