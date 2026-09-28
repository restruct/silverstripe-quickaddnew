jQuery.entwine("quickaddnew", function ($) {
    var fieldSelector = ".field.quickaddnew-field .quickaddnew-field";

    $(".quickaddnew-button").entwine({
        onmatch: function () {
            var self = this;
        },

        onclick: function () {
            this.siblings(fieldSelector).showDialog();
            return false;
        },
    });

    $(fieldSelector).entwine({
        Loading: null,
        Dialog: null,
        URL: null,
        onmatch: function () {
            var self = this;

            //Check to see if quickaddnew has been bound to this field before, sometimes jQuery plugins like Select2
            //will trigger a binding a second time that we don't want.
            if ($(this).parents().children(".quickaddnew-button").length > 0) {
                return;
            }
            // create add new button
            var parentDiv = self.parents("div:first");
            var button = $("<button />")
                .attr("type", "button")
                .attr("href", "#")
                .text(ss.i18n._t("QUICKADDNEW.AddNew"))
                // .addClass("quickaddnew-button ss-ui-button ss-ui-button-small btn btn-secondary")
                // btn-outline-secondary, not btn-secondary: the admin theme overrides btn-secondary
                // to a transparent background AND border, so the trigger read as plain text next to
                // the dropdown. The outline variant keeps a visible border. font-icon-plus adds the
                // admin icon font's "+" (inert on the frontend, where that font is not loaded).
                .addClass("quickaddnew-button btn btn-outline-secondary font-icon-plus")
                .appendTo(parentDiv);

            // create dialog
            var dialog = $("<div />").addClass("quickaddnew-dialog").appendTo(parentDiv);
            this.setDialog(dialog);

            // set URL
            var fieldName = this.attr("name");
            if (this.hasClass("checkboxset")) {
                fieldName = this.find("input:checkbox")
                    .attr("name")
                    .replace(/\[[0-9]+\]/g, "");
            }

            var action = this.parents("form").attr("action").split("?", 2); //add support for url parameters e.g. ?locale=en_US when using Translatable

            var dialogHTMLURL = this.data("quickaddnew-action");
            if (!dialogHTMLURL) {
                // Fallback to default action
                dialogHTMLURL = action[0] + "/field/" + fieldName + "/AddNewFormHTML";
            }
            if (action[1]) {
                dialogHTMLURL += "?" + action[1];
            }
            dialogHTMLURL = dialogHTMLURL.replace(/[\[\]']+/g, "");
            this.setURL(dialogHTMLURL);

            // configure the dialog
            this.getDialog()
                .data("field", this)
                .dialog({
                    autoOpen: false,
                    width: 600,
                    modal: true,
                    resizable: false,
                    title: this.data("dialog-title"),
                    // Scope hook for client/css/quickaddnew.css, so its titlebar/close-button fixes
                    // apply to THIS dialog only and not to every jQuery UI dialog on the page.
                    // (`classes` is the jQuery UI 1.12+ replacement for the deprecated dialogClass;
                    // the admin bundle ships 1.13.) Options are merged per KEY, so a key set here
                    // replaces that key's default: "ui-corner-all" is repeated from jQuery UI's own
                    // dialog defaults.
                    //
                    // No "ui-dialog-titlebar-close" key, deliberately. The admin already renders the
                    // close button's cross as an inner <span class="font-icon-cancel btn__icon">, so
                    // adding font-icon-cancel to the button drew a SECOND X. And SS6's admin sets
                    // its own default for that key ("close btn btn-close btn--no-text btn--icon-xl
                    // modal__close-button"), which a value here would silently replace.
                    classes: {
                        "ui-dialog": "ui-corner-all quickaddnew-ui-dialog",
                        // "ui-dialog-titlebar-close": "font-icon-cancel",
                    },
                    position: { my: "center", at: "center", of: window },
                });

            // handle dialog form submission
            this.getDialog().on("submit", "form", function (e) {
                // Stop the native submit FIRST. The handler used to end in `return false`, so any
                // throw before that line (such as the ajaxSubmit call below) let the browser post
                // the form natively and navigate to the bare AddNewForm response - the field HTML
                // on its own, with the record already written.
                e.preventDefault();

                var form = this;
                var dlg = self.getDialog().dialog();
                var options = {};

                var $submitButtons = $(this).find('input[type="submit"], button[type="submit"]');
                $submitButtons.addClass("loading ui-state-disabled");

                // if this is a multiselect field, send the existing values
                // along with the form submission so they can be included in the
                // replacement field
                if (self.val() && typeof self.val() === "object") {
                    options.data = {
                        existing: self.val().join(","),
                    };
                }
                // if it's a checkboxset field, send the existing values
                // in this case, self is a div
                if (self.hasClass('checkboxset')) {
                    options.data = {
                        existing: self.find('input:checked').map(function() {
                            return this.value;
                        }).get().join()
                    };
                }

                options.success = function (res) {
                    var $response = $(res);
                    if ($response.is(".field")) {
                        self.getDialog().empty().dialog("close");
                        var $newInput = $response.find(self[0].tagName);
                        // Replace <select> <option>'s rather than the entire HTML block
                        // to avoid JS hooks being lost on the frontend.
                        if ($newInput[0] && $newInput[0].tagName === "SELECT") {
                            self.html($newInput.children());

                            // Support legacy and new chosen
                            self.trigger("liszt:updated").trigger("chosen:updated");
                            // Support select2
                            self.trigger("change.select2");
                        } else {
                            self.parents(".field:first").replaceWith(res);
                        }
                    } else {
                        self.getDialog().html(res);
                    }
                };
                options.complete = function () {
                    $submitButtons.removeClass("loading ui-state-disabled");
                };

                // $(this).ajaxSubmit(options);
                // ajaxSubmit() is jquery.form, which is NOT on window.jQuery in the admin: the admin
                // vendor.js bundles jquery.form but applies it to its internal webpack jQuery, not
                // the global one entwine hands us, so $.fn.ajaxSubmit is undefined there and the
                // call threw (browser check, SS5). Post with plain $.ajax + FormData instead, so
                // nothing depends on jquery.form. FormData also carries file inputs, as
                // ajaxSubmit did.
                var formData = new FormData(form);

                // FormData(form) leaves out submit buttons, so add the one that was used (for
                // this form: <button name="action_doAddNew">). FormRequestHandler reads the
                // action_* key to pick the handler; without it, it falls back to the form's
                // first action, which is the same one today, but sending it keeps that explicit.
                var submitter = (e.originalEvent && e.originalEvent.submitter) || $submitButtons.get(0);
                if (submitter && submitter.name && !formData.has(submitter.name)) {
                    formData.append(submitter.name, submitter.value || "");
                }

                // ajaxSubmit merged options.data into the POST; do the same.
                if (options.data) {
                    $.each(options.data, function (key, value) {
                        formData.append(key, value);
                    });
                }

                $.ajax({
                    url: $(form).attr("action"),
                    type: ($(form).attr("method") || "POST").toUpperCase(),
                    data: formData,
                    // Leave FormData alone: no query-string encoding, and let the browser set the
                    // multipart Content-Type with its boundary.
                    processData: false,
                    contentType: false,
                    // HTML, not JSON: FormRequestHandler answers an ajax validation failure with the
                    // re-rendered form as HTML (200) unless the Accept header asks for JSON, and
                    // options.success shows that HTML in the dialog.
                    dataType: "html",
                    success: options.success,
                    error: function (xhr) {
                        // ajaxSubmit had no error handler, so a failed request (permission failure,
                        // server error) did nothing visible. Show the server's response in the
                        // dialog rather than leaving the user waiting.
                        self.getDialog().html(xhr.responseText || (xhr.status + " " + xhr.statusText));
                    },
                    complete: options.complete,
                });

                return false;
            });

            this._super();
        },

        showDialog: function (url) {
            var dlg = this.getDialog();
            // Check to see we have a dialog, other jquery plugins like Select2 can get bound to by accident
            if (dlg !== null) {
                dlg.empty().dialog("open").parent().addClass("loading");

                dlg.load(this.getURL(), function () {
                    dlg.parent().removeClass("loading");
                    // set focus to first input element
                    dlg.find("form :input:visible:enabled:first").focus();
                });
            }
            this._super();
        },
    });
});
