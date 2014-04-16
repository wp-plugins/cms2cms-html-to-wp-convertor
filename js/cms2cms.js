;(function(document, global, $, undefined){

        cms2cms = {
            authentication : '',

            // callbacks
            callback_auth : function ( data ) {
                if ( !data.errors && data.redirect && data.accessKey) {
                    // save key
                    var action_data = {
                        action: 'cms2cms_save_options',
                        accessKey: data.accessKey,
                        login: data.email
                    };
                    $.post(ajaxurl, action_data, function(data) {
                        eval('data = ' + data);
                        if ( !data.errors ) {
                            // reload page to change form and show logout link
                            //location.reload();
                            window.location.href = window.location.pathname + window.location.search;
                        }
                        else {
                            cms2cms.show_error(data.errors);
                            cms2cms.form_loading_hide( $('#cms2cms_form_register') )
                        }
                    });
                } else {
                    if ( data.errors ) {
                        cms2cms.show_error(data.errors);
                        cms2cms.form_loading_hide( $('#cms2cms_form_register') )
                    }
                }
            },

            callback_verify : function ( data ) {
                if ( data.errors ) {
                    cms2cms.show_error(data.errors);
                    cms2cms.form_loading_hide( $('#cms2cms_form_verify') );
                }
                else {
                    cms2cms.hide_error();
                    if ( data.hasOwnProperty('migration') && data.migration != '' ) {
                        var step_run = $('.cms2cms_step_migration_run');

                        if ( data.hasOwnProperty('targetUrl') ) {
                            step_run.find('input[name="targetUrl"]').val(data.targetUrl);
                        }
                        if ( data.hasOwnProperty('targetType') ) {
                            step_run.find('input[name="targetType"]').val(data.targetType);
                        }
                        if ( data.hasOwnProperty('sourceUrl') ) {
                            step_run.find('input[name="sourceUrl"]').val(data.sourceUrl);
                        }
                        if ( data.hasOwnProperty('sourceType') ) {
                            step_run.find('input[name="sourceType"]').val(data.sourceType);
                        }
                        if ( data.hasOwnProperty('migration') ) {
                            step_run.find('input[name="migrationHash"]').val(data.migration);
                        }
                        cms2cms.move( step_run );
                    }
                    else {
                        cms2cms.show_error('Unknown error, please contact us http://www.cms2cms.com/contacts/');
                    }
                }
            },

            // validators
            auth_check_password : function ( form, callback ) {
                var email = form.find('input[name="email"]').val();

                var email_parsed = email.match(/([^\@]+)\@([^\.])/i);
                var name = email_parsed[1].replace(/[^a-z]/i, '') + ' ' + email_parsed[2].replace(/[^a-z]/i, '');
                if ( name == '' ) {
                    name = 'Captain';
                }

                data = decodeURIComponent( form.serialize() );
                data = 'name=' + name + '&' + data;

                cms2cms.form_loading_show(form);
                cms2cms.get_data( form.attr('action'), data, callback );
            },
            verify : function ( form, callback ) {
                cms2cms.form_loading_show(form);
                cms2cms.get_auth('', function() {
                    cms2cms.get_data( form.attr('action'), form.serialize(), callback );
                });
            },

            // move to step form
            move : function ( form ) {
                $('#cms2cms_accordeon').find('form.step_form').each(function(){
                    cms2cms.form_loading_hide( $(this), true );
                    $(this).slideUp('fast');
                });
                form.slideDown('fast');
            },

            // show error
            show_error : function ( error ) {
                var form = $('#cms2cms_accordeon').find('.cms2cms_accordeon_item:visible').find('form');
                var errorText = '';
                if ( typeof(error) == 'object' ) {
                    for ( errorItem in error ) {
                        errorText += errorItem + " : " + error[errorItem] + "<br/>";
                    }
                }
                else {
                    errorText += error;
                }
                form.find('.error_message').html(errorText).show();
            },

            hide_error : function () {
                var form = $('#cms2cms_accordeon').find('.cms2cms_accordeon_item:visible').find('form');
                form.find('.error_message').html('').hide();
            },

            // get auth data
            get_auth : function ( serialized, callback ) {
                var action_data = {
                    action: 'cms2cms_get_options',
                    serialized: serialized
                };
                $.post(ajaxurl, action_data, function(data) {
                    eval('data = ' + data);
                    if ( !data.errors ) {
                        if ( data.accessKey == '' ) {
                            cms2cms.authentication = '';
                        }
                        else {
                            data = JSON.stringify(data);
                            cms2cms.authentication = encodeURIComponent(data);
                            if ( typeof(callback) == 'function' ) {
                                callback();
                            }
                        }
                    }
                    else {
                        cms2cms.show_error(data.errors);
                    }
                });
            },

            // save auth data
            save_auth : function (login, key) {
                var action_data = {
                    action: 'cms2cms_save_options',
                    cms2cms_login: login,
                    cms2cms_key: key
                };

                $.post(ajaxurl, action_data, function(data) {});
            },

            // get data via JSONP
            get_data : function( url, serialized, callback ) {
                var authentication = '';
                if ( cms2cms.authentication != 0 ) {
                    authentication = "authentication=" + cms2cms.authentication;
                }

                global.JSONP(
                    url +
                        "?callback=cms2cms." + callback
                        + "&" + authentication
                        + "&" + serialized
                    , cms2cms[callback]
                );
            },

            // loading animation
            form_loading_show : function ( form, only_spinner ) {
                $(form).closest('li').find('.spinner').css({
                    display: 'inline'
                });
                if ( !only_spinner ) {
                    form.slideUp('fast');
                }
            },

            form_loading_hide : function ( form, only_spinner ) {
                $(form).closest('li').find('.spinner').css({
                    display: 'none'
                });
                if ( !only_spinner ) {
                    form.slideDown('fast');
                }
            },

            pushEvent : function (data){
                if ( !cms2cms.getDisableEventCookie() ) {
                    var form = $('.cms2cms_step_migration_run');
                    var sourceType = form.find('input[name="sourceType"]').val();
                    var targetType = form.find('input[name="targetType"]').val();

                    try {
                        mixpanel.track(data, {
                            type: data,
                            sourceType: sourceType,
                            targetType: targetType
                        });
                    }
                    catch(e){};
                }

            },

            setDisableEventCookie : function(value) {
                var date = new Date();
                date.setTime(date.getTime()+(1000*24*60*60*1000));
                var expires = "; expires="+date.toGMTString();

                value = value || 0;

//                var d = new Date();
//                var value = d.getTime();

                var domain = document.location.hostname;

                document.cookie = "cms2cms_disable_events=" + value +
                    "; expires=" + expires  +
                    "; path=" + "/" +
                    "; domain=" + domain;
            },

            getDisableEventCookie : function () {
                var cookie = " " + document.cookie;
                var search = " cms2cms_disable_events=";
                var setStr = null;
                var offset = 0;
                var end = 0;
                if (cookie.length > 0) {
                    offset = cookie.indexOf(search);
                    if (offset != -1) {
                        offset += search.length;
                        end = cookie.indexOf(";", offset);
                        if (end == -1) {
                            end = cookie.length;
                        }
                        setStr = unescape(cookie.substring(offset, end));
                    }
                }
                return(setStr);
            }



    };

        $(document).ready(function(){

            var cms2cmsWrapper = $('.wrap.cms2cms-wrapper');
            var cms2cmsBlock = $('#cms2cms_accordeon');

            // Change tabs Register and Login
            var signInTabs = cms2cmsBlock.find('a.nav-tab');
            signInTabs.on('click', function(e) {
                if ( !$(this).hasClass('cms2cms-real-link') ) {
                    e.preventDefault();
                    var activeClass = 'nav-tab-active';
                    if ( !$(this).hasClass(activeClass) ) {
                        signInTabs.removeClass(activeClass);
                        $(this).addClass(activeClass);
                        $(this).closest('.cms2cms_accordeon_item').find('form').attr('action', $(this).attr('href'));
                    }
                }
            });

            // Assign forms to JSONP
            cms2cmsBlock.find('form').on('submit', function(e) {
                var callback =  $(this).attr('callback');
                var validate =  $(this).attr('validate');

                $(this).find('.error_message').html('').hide();

                if ( callback && typeof(cms2cms[callback]) == 'function' ) {
                    if ( validate && typeof(cms2cms[validate]) == 'function' ) {
                        e.preventDefault();
                        cms2cms[validate]($(this), callback);
                    }
                }
            });

            // Send the event
            cms2cmsWrapper.find('[data-log-this]').not('input,textarea,select').on('click', function(e) {
                cms2cms.pushEvent( $(this).data('log-this') );
            });

            cms2cmsWrapper.on('blur', 'input[data-log-this], textarea[data-log-this], select[data-log-this]', function(e) {
                cms2cms.pushEvent( $(this).data('log-this') );
            });

            // Disable events
            cms2cmsWrapper.find('.stop-events').on('click', function(e) {
                e.preventDefault();
                cms2cms.setDisableEventCookie(1);
                $('#cms2cms-allowed-events').hide();
                $('#cms2cms-disabled-events').show();
            });

            // Allow events
            cms2cmsWrapper.find('.allow-events').on('click', function(e) {
                e.preventDefault();
                cms2cms.setDisableEventCookie(0);
                $('#cms2cms-allowed-events').show();
                $('#cms2cms-disabled-events').hide();
            });

            // check if allowed events on load
            if ( cms2cms.getDisableEventCookie() ) {
                $('#cms2cms-allowed-events').show();
                $('#cms2cms-disabled-events').hide();
            }
            else {
                $('#cms2cms-allowed-events').hide();
                $('#cms2cms-disabled-events').show();
            }

        });
    })(document, window, jQuery);
