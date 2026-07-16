/**
 * Mobile Filters & Responsive Tables
 * Combined functionality for GridView filters and action-details width control
 */

(function($) {
    'use strict';

    /**
     * ========================================
     * MOBILE SIDEBAR FIX FOR ADMINLTE
     * Register early to prevent AdminLTE interference
     * ========================================
     */

    // Register handler immediately when jQuery is ready
    $(function() {
        // Disable AdminLTE PushMenu on mobile by removing data-widget temporarily
        if ($(window).width() <= 991) {
            var $pushMenuBtn = $('[data-widget="pushmenu"]');
            $pushMenuBtn.attr('data-widget-original', 'pushmenu');
            $pushMenuBtn.removeAttr('data-widget');
        }

        // Handle pushmenu button on mobile with our own handler
        $(document).on('click', '[data-widget-original="pushmenu"]', function(e) {
            if ($(window).width() <= 991) {
                e.preventDefault();
                e.stopPropagation();
                e.stopImmediatePropagation();

                var $body = $('body');
                var isOpen = $body.hasClass('sidebar-open');

                if (isOpen) {
                    // Close sidebar
                    $body.removeClass('sidebar-open');
                } else {
                    // Open sidebar immediately
                    $body.addClass('sidebar-open');

                    // Setup close handler for next click outside
                    setTimeout(function() {
                        $(document).one('click', function(event) {
                            if (!$(event.target).closest('.main-sidebar').length &&
                                !$(event.target).closest('[data-widget-original="pushmenu"]').length) {
                                $body.removeClass('sidebar-open');
                            }
                        });
                    }, 100);
                }

                return false;
            }
        });

        // Also handle original data-widget in case it wasn't removed
        $(document).on('click', '[data-widget="pushmenu"]', function(e) {
            if ($(window).width() <= 991) {
                e.preventDefault();
                e.stopPropagation();
                e.stopImmediatePropagation();

                var $body = $('body');
                if (!$body.hasClass('sidebar-open')) {
                    $body.addClass('sidebar-open');

                    setTimeout(function() {
                        $(document).one('click', function(event) {
                            if (!$(event.target).closest('.main-sidebar').length &&
                                !$(event.target).closest('[data-widget="pushmenu"]').length) {
                                $body.removeClass('sidebar-open');
                            }
                        });
                    }, 100);
                } else {
                    $body.removeClass('sidebar-open');
                }

                return false;
            }
        });

        // Close sidebar when clicking on menu item (mobile only)
        $(document).on('click', '.main-sidebar .nav-link', function(e) {
            if ($(window).width() <= 991) {
                if (!$(this).parent().hasClass('has-treeview')) {
                    setTimeout(function() {
                        $('body').removeClass('sidebar-open');
                    }, 150);
                }
            }
        });

        // Close sidebar when clicking close button (mobile only)
        $(document).on('click', '.sidebar-close-btn', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $('body').removeClass('sidebar-open');
        });

        // Handle window resize
        $(window).on('resize', function() {
            var $pushMenuBtn = $('[data-widget-original="pushmenu"]');

            if ($(window).width() > 991) {
                $('body').removeClass('sidebar-open');
                $('body').addClass('sidebar-mini sidebar-closed sidebar-collapse');
                // Restore data-widget on desktop
                if ($pushMenuBtn.length && $pushMenuBtn.attr('data-widget-original')) {
                    $pushMenuBtn.attr('data-widget', 'pushmenu');
                }
            } else {
                // Remove data-widget on mobile
                if ($pushMenuBtn.length) {
                    $pushMenuBtn.removeAttr('data-widget');
                } else {
                    $('[data-widget="pushmenu"]').each(function() {
                        $(this).attr('data-widget-original', 'pushmenu');
                        $(this).removeAttr('data-widget');
                    });
                }
            }
        });
    });

    /**
     * Mobile Filters - Offcanvas functionality for GridView filters
     */
    window.MobileFilters = {
        /**
         * Initialize offcanvas filters
         */
        init: function() {
            this.createOffcanvasStructure();
            this.bindEvents();
        },

        /**
         * Create offcanvas HTML structure
         */
        createOffcanvasStructure: function() {
            // Check if already exists
            if ($('#offcanvasFilter').length) {
                return;
            }

            var offcanvas = $('<div class="offcanvas-filter" id="offcanvasFilter"></div>');
            var backdrop = $('<div class="offcanvas-backdrop" id="offcanvasBackdrop"></div>');

            var header = $('<div class="offcanvas-header">' +
                '<h3><i class="fas fa-filter"></i> Filters</h3>' +
                '<button class="offcanvas-close">&times;</button>' +
                '</div>');
            var body = $('<div class="offcanvas-body"></div>');
            var footer = $('<div class="offcanvas-footer">' +
                '<button class="btn-apply-filters">Apply Filters</button>' +
                '<button class="btn-reset-filters">Reset All</button>' +
                '</div>');

            offcanvas.append(header).append(body).append(footer);
            $('body').append(backdrop).append(offcanvas);
        },

        /**
         * Populate filters from table headers
         */
        populateFilters: function() {
            var filterBody = $('#offcanvasFilter .offcanvas-body');
            filterBody.empty();

            // Find all thead rows
            var allRows = $('.table thead tr');
            var filterRow = null;
            var headerRow = null;

            // GridView creates 2 rows: first with headers, second with filters
            if (allRows.length >= 2) {
                headerRow = allRows.eq(0);
                filterRow = allRows.eq(1);
            }

            if (!filterRow || filterRow.length === 0) {
                filterBody.append('<p style="padding: 20px; text-align: center; color: #999;">No filters available</p>');
                return;
            }

            // Iterate through filter cells
            filterRow.find('th, td').each(function(index) {
                var filterCell = $(this);

                // Find input or select in this cell
                var input = filterCell.find('input').first();
                var select = filterCell.find('select').first();

                if (input.length > 0 || select.length > 0) {
                    // Get label from header
                    var headerCell = headerRow.find('th, td').eq(index);
                    var labelText = headerCell.find('a').first().text().trim();
                    if (!labelText) {
                        labelText = headerCell.clone().children().remove().end().text().trim();
                    }

                    if (labelText && labelText !== '') {
                        var filterGroup = $('<div class="offcanvas-filter-group"></div>');
                        filterGroup.append('<label>' + labelText + '</label>');

                        if (input.length > 0) {
                            // Clone input field
                            var newInput = $('<input>')
                                .addClass('form-control')
                                .attr({
                                    'type': input.attr('type') || 'text',
                                    'name': input.attr('name'),
                                    'placeholder': input.attr('placeholder') || '',
                                    'value': input.val()
                                });

                            filterGroup.append(newInput);
                        } else if (select.length > 0) {
                            // Clone select field
                            var newSelect = $('<select>').addClass('form-control').attr('name', select.attr('name'));

                            // Copy all options
                            select.find('option').each(function() {
                                var option = $(this);
                                newSelect.append(
                                    $('<option>')
                                        .val(option.val())
                                        .text(option.text())
                                        .prop('selected', option.prop('selected'))
                                );
                            });

                            filterGroup.append(newSelect);
                        }

                        filterBody.append(filterGroup);
                    }
                }
            });

            var groupCount = filterBody.find('.offcanvas-filter-group').length;

            if (groupCount === 0) {
                filterBody.append('<p style="padding: 20px; text-align: center; color: #999;">No filters configured</p>');
            }
        },

        /**
         * Open offcanvas panel
         */
        open: function() {
            this.populateFilters();
            $('#offcanvasBackdrop').addClass('show');
            setTimeout(function() {
                $('#offcanvasFilter').addClass('show');
            }, 10);
            $('body').css('overflow', 'hidden');
        },

        /**
         * Close offcanvas panel
         */
        close: function() {
            $('#offcanvasFilter').removeClass('show');
            setTimeout(function() {
                $('#offcanvasBackdrop').removeClass('show');
                $('body').css('overflow', '');
            }, 300);
        },

        /**
         * Apply filters from offcanvas to table
         */
        applyFilters: function() {
            // Copy values from offcanvas filters back to original filters
            $('#offcanvasFilter .offcanvas-filter-group').each(function() {
                var offcanvasInput = $(this).find('input, select');
                var inputName = offcanvasInput.attr('name');

                if (inputName) {
                    var originalInput = $('.table thead').find('[name="' + inputName + '"]');
                    if (originalInput.length) {
                        originalInput.val(offcanvasInput.val());
                    }
                }
            });

            // Submit the form
            var form = $('.table').closest('form');
            if (form.length) {
                form.submit();
            } else {
                window.location.href = window.location.href.split('?')[0] + '?' +
                    $('.table thead input, .table thead select').serialize();
            }

            this.close();
        },

        /**
         * Reset all filters
         */
        resetFilters: function() {
            // Clear offcanvas filter values
            $('#offcanvasFilter .offcanvas-filter-group input').val('');
            $('#offcanvasFilter .offcanvas-filter-group select').prop('selectedIndex', 0);

            // Clear original filters
            $('.table thead input').val('');
            $('.table thead select').prop('selectedIndex', 0);

            // Submit to reset
            var form = $('.table').closest('form');
            if (form.length) {
                form.submit();
            } else {
                window.location.href = window.location.href.split('?')[0];
            }

            this.close();
        },

        /**
         * Bind all events
         */
        bindEvents: function() {
            var self = this;

            // Open offcanvas
            $(document).on('click', '.mobile-filter-btn', function(e) {
                e.preventDefault();
                self.open();
            });

            // Close offcanvas
            $(document).on('click', '.offcanvas-close, .offcanvas-backdrop', function(e) {
                self.close();
            });

            // Apply filters
            $(document).on('click', '.btn-apply-filters', function() {
                self.applyFilters();
            });

            // Reset filters
            $(document).on('click', '.btn-reset-filters', function() {
                self.resetFilters();
            });
        }
    };

    /**
     * Add data-label attributes to table cells for mobile view
     */
    window.MobileFilters.addDataLabels = function() {
        var labels = [];

        // Find header row (the one without inputs/selects)
        var headerRow = null;
        $('.table thead tr').each(function() {
            if ($(this).find('input, select').length === 0) {
                headerRow = $(this);
                return false; // break
            }
        });

        if (!headerRow || headerRow.length === 0) {
            headerRow = $('.table thead tr').first();
        }

        headerRow.find('th').each(function() {
            var label = $(this).find('a').first().text().trim();
            if (!label) {
                label = $(this).clone().children().remove().end().text().trim();
            }
            labels.push(label);
        });

        $('.table tbody tr').not('.action-details-row').each(function() {
            $(this).find('td').each(function(index) {
                if (labels[index] && labels[index] !== '') {
                    $(this).attr('data-label', labels[index]);
                }
            });
        });
    };

    /**
     * ========================================
     * ACTION DETAILS WIDTH CONTROL
     * Critical function to prevent horizontal overflow
     * ========================================
     */
    window.forceActionDetailsWidth = function() {
        if ($(window).width() <= 768) {
            $('.action-details').each(function() {
                var $actionDetails = $(this);

                // Remove any inline width styles that might cause overflow
                $actionDetails.css({
                    'width': '100%',
                    'max-width': '100%',
                    'min-width': '0',
                    'overflow': 'hidden'
                });

                // Force all form controls to 100% width
                $actionDetails.find('input, select, textarea, .form-control').each(function() {
                    var $input = $(this);
                    $input.css({
                        'width': '100%',
                        'max-width': '100%',
                        'min-width': '0',
                        'box-sizing': 'border-box'
                    });

                    // Remove inline width attributes that override CSS
                    if ($input.attr('style') && $input.attr('style').indexOf('width') > -1) {
                        var style = $input.attr('style');
                        style = style.replace(/width\s*:\s*[^;]+;?/gi, '');
                        $input.attr('style', style);
                    }
                });

                // Fix input groups (API key section with Generate/Copy buttons)
                $actionDetails.find('.input-group').each(function() {
                    $(this).css({
                        'width': '100%',
                        'max-width': '100%',
                        'display': 'flex',
                        'flex-direction': 'column',
                        'gap': '10px'
                    });
                });

                $actionDetails.find('.input-group-append').each(function() {
                    $(this).css({
                        'width': '100%',
                        'display': 'flex',
                        'margin-left': '0',
                        'gap': '10px'
                    });

                    $(this).find('.btn').css({
                        'flex': '1',
                        'min-width': '0'
                    });
                });

                // Fix form groups
                $actionDetails.find('.form-group, [class*="field-"]').each(function() {
                    $(this).css({
                        'width': '100%',
                        'max-width': '100%',
                        'overflow': 'hidden'
                    });
                });

                // Fix containers
                $actionDetails.find('.container-fluid, .row, [class*="col-"]').each(function() {
                    $(this).css({
                        'max-width': '100%',
                        'overflow': 'hidden'
                    });
                });

                // Fix labels and text elements - ensure they wrap
                $actionDetails.find('label, .control-label, .hint-block, .help-block').each(function() {
                    $(this).css({
                        'word-wrap': 'break-word',
                        'overflow-wrap': 'break-word',
                        'white-space': 'normal',
                        'max-width': '100%'
                    });
                });
            });
        }
    };

    /**
     * Observe DOM changes in action-details
     * This ensures width constraints are applied even to dynamically loaded content
     */
    if (window.MutationObserver) {
        var observer = new MutationObserver(function(mutations) {
            var shouldUpdate = false;

            mutations.forEach(function(mutation) {
                if (mutation.addedNodes.length) {
                    mutation.addedNodes.forEach(function(node) {
                        if (node.nodeType === 1) { // Element node
                            if ($(node).hasClass('action-details') ||
                                $(node).find('.action-details').length ||
                                $(node).closest('.action-details').length) {
                                shouldUpdate = true;
                            }
                        }
                    });
                }
            });

            if (shouldUpdate) {
                setTimeout(function() {
                    window.forceActionDetailsWidth();
                }, 100);
            }
        });

        // Start observing when document is ready
        $(document).ready(function() {
            observer.observe(document.body, {
                childList: true,
                subtree: true
            });
        });
    }

    /**
     * Initialize all mobile features on document ready
     */
    $(document).ready(function() {
        // Initialize mobile filters
        window.MobileFilters.init();
        window.MobileFilters.addDataLabels();

        // Apply width constraints to any existing action-details
        window.forceActionDetailsWidth();

        // Re-apply on window resize
        var resizeTimeout;
        $(window).on('resize', function() {
            clearTimeout(resizeTimeout);
            resizeTimeout = setTimeout(function() {
                window.forceActionDetailsWidth();
            }, 250);
        });
    });

    /**
     * Re-initialize after pjax updates
     */
    $(document).on('pjax:success', function() {
        window.MobileFilters.addDataLabels();
        setTimeout(function() {
            window.forceActionDetailsWidth();
        }, 100);
    });

    /**
     * Export functions for use in other scripts
     */
    window.MobileResponsive = {
        forceActionDetailsWidth: window.forceActionDetailsWidth,
        addDataLabels: window.MobileFilters.addDataLabels,
        init: function() {
            window.MobileFilters.init();
            window.MobileFilters.addDataLabels();
            window.forceActionDetailsWidth();
        }
    };

})(jQuery);