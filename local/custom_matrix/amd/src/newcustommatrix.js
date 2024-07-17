/**
 * Add a create new group modal to the page.
 *
 * @module     local_costcenter/costcenter
 * @class      NewCostcenter
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery', 'core/str', 'core/modal_factory', 'core/modal_events', 'core/fragment', 'core/ajax', 'core/yui'],
        function($, Str, ModalFactory, ModalEvents, Fragment, Ajax, Y) {

    /**
     * Constructor
     * @type {args}
     * Each call to init gets it's own instance of this class.
     */
    var Newcustommatrix = function(args) {
        this.contextid = args.contextid;
        this.repositoryid = args.repositoryid;
        this.parentcatid = args.parentcatid;
        var self = this;
        self.init(args);
    };

    /**
     * @var {Modal} modal
     * @private
     */
    Newcustommatrix.prototype.modal = null;

    /**
     * @var {int} contextid
     * @private
     */
    Newcustommatrix.prototype.contextid = -1;

    /**
     * Initialise the class.
     * @type {args}
     */
    Newcustommatrix.prototype.init = function(args) {
        var self = this;

        // Fetch the title string.
        var editid = $(this).data('value');
        if (editid) {
            self.repositoryid = editid;
        }
        if(self.parentcatid){
            if(this.repositoryid){
                var head = Str.get_string('updatecusmatrixperformance', 'local_custom_matrix');
            }else{
                var head = Str.get_string('addmatrixperformance', 'local_custom_matrix');
            }
        }else
        {
            if(this.repositoryid){
                var head = Str.get_string('updatecusmatrixtype', 'local_custom_matrix');
            }else{
                var head = Str.get_string('addmatrixtype', 'local_custom_matrix');
            }
        }
        return head.then(function(title) {
            // Create the modal.
            return ModalFactory.create({
                type: ModalFactory.types.SAVE_CANCEL,
                title: title,
                body: self.getBody()
            });
        }.bind(self)).then(function(modal) {

            // Keep a reference to the modal.
            self.modal = modal;
            // Forms are big, we want a big modal.
            self.modal.setLarge();
            this.modal.getRoot().addClass('openLMStransition local_custom_matrix');

            // We want to reset the form every time it is opened.
             this.modal.getRoot().on(ModalEvents.hidden, function() {
                this.modal.getRoot().animate({"right":"-85%"}, 500);
                setTimeout(function(){
                    modal.destroy();
                }, 1000);
            }.bind(this));
            self.modal.getRoot().on(ModalEvents.hidden, function() {
                modal.destroy();
            }.bind(this));
            self.modal.getRoot().on(ModalEvents.shown, function() {
                self.modal.getRoot().append('<style>[data-fieldtype=submit] { display: none ! important; }</style>');
                this.modal.getFooter().find('[data-action="cancel"]').on('click', function() {
                    modal.hide();
                    setTimeout(function(){
                        modal.destroy();
                    }, 1000);
                });
            }.bind(this));

            // We catch the modal save event, and use it to submit the form inside the modal.
            // Triggering a form submission will give JS validation scripts a chance to check for errors.
            self.modal.getRoot().on(ModalEvents.save, self.submitForm.bind(self));
            // We also catch the form submit event and use it to submit the form with ajax.
            self.modal.getRoot().on('submit', 'form', self.submitFormAjax.bind(self));
            self.modal.show();
            this.modal.getRoot().animate({"right":"0%"}, 500);
            return this.modal;
        }.bind(this));
    };

    /**
     * @method getBody
     * @private
     * @type {args}
     * @return {Promise}
     */
    Newcustommatrix.prototype.getBody = function(formdata) {
        if (typeof formdata === "undefined") {
            formdata = {};
        }
        // Get the content of the modal.
        var params = {repositoryid:this.repositoryid, jsonformdata: JSON.stringify(formdata), parentcatid:this.parentcatid};
        return Fragment.loadFragment('local_custom_matrix', 'new_custom_matrix_form', this.contextid, params);
    };

    /**
     * @method handleFormSubmissionResponse
     * @private
     * @return {Promise}
     */
    Newcustommatrix.prototype.handleFormSubmissionResponse = function() {
        this.modal.hide();
        // We could trigger an event instead.
        // Yuk.
        Y.use('moodle-core-formchangechecker', function() {
            M.core_formchangechecker.reset_form_dirty_state();
        });
        document.location.reload();
    };

    /**
     * @method handleFormSubmissionFailure
     * @private
     * @type {data}
     * @return {Promise}
     */
    Newcustommatrix.prototype.handleFormSubmissionFailure = function(data) {
        // Oh noes! Epic fail :(
        // Ah wait - this is normal. We need to re-display the form with errors!
        this.modal.setBody(this.getBody(data));
    };

    /**
     * Private method
     *
     * @method submitFormAjax
     * @private
     * @param {Event} e Form submission event.
     */
    Newcustommatrix.prototype.submitFormAjax = function(e) {
        // We don't want to do a real form submission.
        e.preventDefault();

        // Convert all the form elements values to a serialised string.
        var formData = this.modal.getRoot().find('form').serialize();

        // Now we can continue...
        Ajax.call([{
            methodname: 'local_custom_matrix_submit_custom_matrix_form',
            args: {contextid: this.contextid, jsonformdata: formData},
            done: this.handleFormSubmissionResponse.bind(this, formData),
            fail: this.handleFormSubmissionFailure.bind(this, formData)
        }]);
    };

    /**
     * This triggers a form submission, so that any mform elements can do final tricks before the form submission is processed.
     *
     * @method submitForm
     * @param {Event} e Form submission event.
     * @private
     */
    Newcustommatrix.prototype.submitForm = function(e) {
        e.preventDefault();
        var self = this;
        self.modal.getRoot().find('form').submit();
    };

    return /** @alias module:local_costcenter/newcostcenter */ {
        // Public variables and functions.
        /**
         * Attach event listeners to initialise this module.
         *
         * @method init
         * @type {args}
         * @return {Promise}
         */
        init: function(args) {
            return new Newcustommatrix(args);
        },
        load: function(){

        },
        deletematrix: function(args) {


            return Str.get_strings([{
                key: 'confirm'
            },
            {
                key: 'deletematrix',
                component: 'local_custom_matrix',
                param :args
            },
            {
                key: 'no',
                component: 'local_custom_matrix',
                param :args
            },
            {
                key: 'yesdelete',
                component: 'local_custom_matrix',
                param :args
            }]).then(function(s) {
                ModalFactory.create({
                    title: s[0],
                    type: ModalFactory.types.DEFAULT,
                    body: s[1],
                     footer: '<button type="button" class="btn btn-primary" data-action="save">'+s[3]+'</button>&nbsp;' +
                     '<button type="button" class="btn btn-secondary" data-action="cancel">'+s[2]+'</button>'
                })

                .done(function(modal) {
                    this.modal = modal;

                    modal.getRoot().find('[data-action="save"]').on('click', function() {
                        args.confirm = true;
                        $.ajax({
                            method: "POST",
                            dataType: "json",
                            url: M.cfg.wwwroot + "/local/custom_matrix/ajax.php?action="+args.selector+"&matrixid="+args.matrixid,
                            success: function(){
                                window.location.reload();
                            }
                        });

                    }.bind(this));
                    modal.getFooter().find('[data-action="cancel"]').on('click', function() {
                        modal.setBody('');
                        modal.hide();
                    });
                    modal.show();
                }.bind(this));
            }.bind(this));
        },
        nodelete: function(args) {

            if(args.parentcatid){
                var head = Str.get_string('nodeletecatchild', 'local_custom_matrix', args);
            }else{
                var head = Str.get_string('nodeletecattop', 'local_custom_matrix', args);
            }
            return head.then(function(body) {
                return ModalFactory.create({
                    title: Str.get_string('reason', 'local_custom_matrix'),
                    type: ModalFactory.types.DEFAULT,
                    body: body,
                });

            }.bind(self)).done(function(modal) {
                this.modal = modal;
                modal.show();
            }.bind(this));
        },
    };
});
