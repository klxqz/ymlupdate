$.extend($.importexport.plugins, {
    ymlupdate: {
        form: null,
        ajax_pull: {},
        progress: false,
        id: null,
        debug: {
            'memory': 0.0,
            'memory_avg': 0.0
        },
        data: {
            'params': {}
        },
        $form: null,
        init: function (data) {
            this.$form = $("#s-plugin-ymlupdate");
            $.extend(this.data, data);
            this.uploadInit();
        },
        hashAction: function (hash) {
            $.importexport.products.action(hash);
            window.location.hash = window.location.hash.replace(/\/hash\/.+$/, '/');
        },
        action: function () {

        },
        blur: function () {

        },

        uploadInit: function () {

            var url = this.$form.find('.fileupload:first').data('action');

            var upload = this.$form.find('.fileupload:first').parents('div.field');

            var uploadgroup = upload.parents('div.field-group');
            
            var self = this;
            this.$form.find('.fileupload:first').fileupload({
                url: url,
                start: function () {
                    upload.find('.fileupload:first').hide();
                    upload.find('.js-fileupload-progress').show();
                    //uploadgroup.next('div.field-group').hide();
                },
                done: function (e, data) {
                    upload.find('.js-fileupload-progress').html('<i class="icon16 yes"></i>');
                    //uploadgroup.next('div.field-group').show();
                },
                fail: function (e, data) {
                    upload.find('.js-fileupload-progress').html('<i class="icon16 no"></i>');
                }
            });

        },
        actionHandler: function ($el) {
            try {
                var args = $el.attr('href').replace(/.*#\/?/, '').replace(/\/$/, '').split('/');
                args.shift();
                var method = $.shop.getMethod(args, this);

                if (method.name) {
                    $.shop.trace('$.importexport.plugins.ymlupdate', method);
                    if (!$el.hasClass('js-confirm') || confirm($el.data('confirm-text') || $el.attr('title') || 'Are you sure?')) {
                        method.params.unshift($el);
                        this[method.name].apply(this, method.params);
                    }
                } else {
                    $.shop.error('Not found js handler for link', [method, args, $el])
                }
            } catch (e) {
                $.shop.error('Exception ' + e.message, e);
            }
            return false;
        },
        initForm: function () {

            this.$form.unbind('submit.ymlupdate').bind('submit.ymlupdate', function (event) {
                $.shop.trace('submit.ymlupdate ' + event.namespace, event);
                try {
                    var $form = $(this);
                    $form.find(':input, :submit').attr('disabled', false);
                    $.importexport.plugins.ymlupdate.ymlupdateHandler(this);
                } catch (e) {
                    $('#plugin-ymlupdate-transport-group').find(':input').attr('disabled', false);
                    $.shop.error('Exception: ' + e.message, e);
                }
                return false;
            });


        },
        onInit: function () {
            this.initForm();
        },
        ymlupdateHandler: function (element) {
            this.form = $(element);
            /**
             * reset required form fields errors
             */
            this.form.find('.value.js-required :input.error').removeClass('error');
            /**
             * verify form
             */
            var valid = true;
            this.form.find('.value.js-required :input:visible:not(:disabled)').each(function () {
                var $this = $(this);
                var value = $this.val();
                if (!value || (value == 'skip:')) {
                    $this.addClass('error');
                    valid = false;
                }
            });
            if (!valid) {
                var $target = this.form.find('.value.js-required :input.error:first');

                $('html, body').animate({
                    scrollTop: $target.offset().top - 10
                }, 1000, function () {
                    $target.focus();
                });
                this.form.find(':input, :submit').attr('disabled', null);
                return false;
            }

            this.progress = true;

            var data = this.form.serialize();
            this.form.find('.errormsg').text('');
            this.form.find(':input').attr('disabled', true);
            this.form.find('a.js-action:visible').data('visible', 1).hide();
            this.form.find(':submit').hide();
            this.form.find('.progressbar .progressbar-inner').css('width', '0%');
            this.form.find('.progressbar').show();
            var url = $(element).attr('action');
            var self = this;
            $.ajax({
                url: url,
                data: data,
                dataType: 'json',
                type: 'post',
                success: function (response) {
                    if (response.error) {
                        self.form.find(':input').attr('disabled', false);
                        self.form.find(':submit').show();
                        self.form.find('a.js-action:hidden').each(function () {
                            var $this = $(this);
                            if ($this.data('visible')) {
                                $this.show();
                                $this.data('visible', null);
                            }
                        });
                        self.form.find('.js-progressbar-container').hide();
                        self.form.find('.shop-ajax-status-loading').remove();
                        self.progress = false;
                        self.form.find('.errormsg').text(response.error);
                    } else {
                        self.form.find('.progressbar').attr('title', '0.00%');
                        self.form.find('.progressbar-description').text('0.00%');
                        self.form.find('.js-progressbar-container').show();

                        self.ajax_pull[response.processId] = [];
                        self.ajax_pull[response.processId].push(setTimeout(function () {
                            $.wa.errorHandler = function (xhr) {
                                return !((xhr.status >= 500) || (xhr.status == 0));
                            };
                            self.progressHandler(url, response.processId, response);
                        }, 2100));
                        self.ajax_pull[response.processId].push(setTimeout(function () {
                            self.progressHandler(url, response.processId, null);
                        }, 5500));
                    }
                },
                error: function () {
                    self.form.find(':input').attr('disabled', false);
                    self.form.find('a.js-action:hidden').each(function () {
                        var $this = $(this);
                        if ($this.data('visible')) {
                            $this.show();
                            $this.data('visible', null);
                        }
                    });
                    self.form.find(':submit').show();
                    self.form.find('.js-progressbar-container').hide();
                    self.form.find('.shop-ajax-status-loading').remove();
                    self.form.find('.progressbar').hide();
                }
            });
            return false;
        },
        onDone: function (url, processId, response) {

        },
        progressHandler: function (url, processId, response) {
            // display progress
            // if not completed do next iteration
            var self = $.importexport.plugins.ymlupdate;
            var $bar;
            if (response && response.ready) {
                $.wa.errorHandler = null;
                var timer;
                while (timer = self.ajax_pull[processId].pop()) {
                    if (timer) {
                        clearTimeout(timer);
                    }
                }
                $bar = self.form.find('.progressbar .progressbar-inner');
                $bar.css({
                    'width': '100%'
                });
                $.shop.trace('cleanup', response.processId);


                $.ajax({
                    url: url,
                    data: {
                        'processId': response.processId,
                        'cleanup': 1
                    },
                    dataType: 'json',
                    type: 'post',
                    success: function (response) {
                        $.shop.trace('report', response);
                        $("#plugin-ymlupdate-submit").hide();
                        self.form.find('.progressbar').hide();
                        var $report = $("#plugin-ymlupdate-report");
                        $report.show();
                        if (response.report) {
                            $report.find(".value:first").html(response.report);
                        }
                        $.storage.del('shop/hash');
                    }
                });

            } else if (response && response.error) {

                self.form.find(':input').attr('disabled', false);
                self.form.find(':submit').show();
                self.form.find('.js-progressbar-container').hide();
                self.form.find('.shop-ajax-status-loading').remove();
                self.form.find('.progressbar').hide();
                self.form.find('.errormsg').text(response.error);

            } else {
                var $description;
                if (response && (typeof (response.progress) != 'undefined')) {
                    $bar = self.form.find('.progressbar .progressbar-inner');
                    var progress = parseFloat(response.progress.replace(/,/, '.'));
                    $bar.animate({
                        'width': progress + '%'
                    });
                    self.debug.memory = Math.max(0.0, self.debug.memory, parseFloat(response.memory) || 0);
                    self.debug.memory_avg = Math.max(0.0, self.debug.memory_avg, parseFloat(response.memory_avg) || 0);

                    var title = 'Memory usage: ' + self.debug.memory_avg + '/' + self.debug.memory + 'MB';
                    title += ' (' + (1 + response.stage_num) + '/' + (parseInt(response.stage_count)) + ')';

                    var message = response.progress + ' — ' + response.stage_name;

                    $bar.parents('.progressbar').attr('title', response.progress);
                    $description = self.form.find('.progressbar-description');
                    $description.text(message);
                    $description.attr('title', title);
                }
                if (response && (typeof (response.warning) != 'undefined')) {
                    $description = self.form.find('.progressbar-description');
                    $description.append('<i class="icon16 exclamation"></i><p>' + response.warning + '</p>');
                }

                var ajax_url = url;
                var id = processId;

                self.ajax_pull[id].push(setTimeout(function () {
                    $.ajax({
                        url: ajax_url,
                        data: {
                            'processId': id
                        },
                        dataType: 'json',
                        type: 'post',
                        success: function (response) {
                            self.progressHandler(url, response ? response.processId || id : id, response);
                        },
                        error: function () {
                            self.progressHandler(url, id, null);
                        }
                    });
                }, 2000));
            }
        },
        getLink: function () {
            window.location.reload();

        },
        helpers: {
            /**
             * Compile jquery templates
             *
             * @param {String=} selector optional selector of template container
             */
            compileTemplates: function (selector) {
                var pattern = /<\\\/(\w+)/g;
                var replace = '</$1';

                $(selector || '*').find("script[type$='x-jquery-tmpl']").each(function () {
                    var id = $(this).attr('id').replace(/-template-js$/, '');
                    var template = $(this).html().replace(pattern, replace);
                    try {
                        $.template(id, template);
                        $.shop && $.shop.trace('$.importexport.plugins.helper.compileTemplates', [selector, id]);
                    } catch (e) {
                        (($.shop && $.shop.error) || console.log)('compile template ' + id + ' at ' + selector + ' (' + e.message + ')', template);
                    }
                });
            }
        }
    }
});
