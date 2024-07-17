define([
    'local_positions/jquery.dataTables',
    'core/str',
    'core/modal_factory',
    'core/modal_events',
    'core/ajax',
    'core/fragment',
    'jquery',
    'jqueryui',
], function (dataTable, Str, ModalFactory, ModalEvents, Ajax, Fragment, $) {
    var Newdomain = function(args){
            this.args = args;
            var self = this;
            self.init(args);
        };

        /**
         * @var {Modal} modal
         * @private
         */
        Newdomain.prototype.modal = null;
     
        /**
         * @var {int} contextid
         * @private
         */
        Newdomain.prototype.contextid = -1;

        Newdomain.prototype.init = function(args) {
            // console.log(args);
            //var triggers = $(selector);
            var self = this;
            if(args.domainid){
                var head = Str.get_string('editdomain', 'local_positions');
            }else{
                var head = Str.get_string('createdomain', 'local_positions');
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
                this.modal.getRoot().addClass('openLMStransition local_positions');
     
                // We want to reset the form every time it is opened.
                this.modal.getRoot().on(ModalEvents.hidden, function() {
                    this.modal.getRoot().animate({"right":"-85%"}, 500);
                    setTimeout(function(){
                        modal.destroy();
                    }, 100);
                }.bind(this));
                // self.modal.getRoot().on(ModalEvents.hidden, function() {
                //     modal.hide();
                //         setTimeout(function(){
                //             modal.destroy();
                //         }, 5000);
                //     //     self.modal.setBody(self.getBody());
                //     }.bind(this));
                        self.modal.getRoot().on(ModalEvents.shown, function() {
                        self.modal.getRoot().append('<style>[data-fieldtype=submit] { display: none ! important; }</style>');
                        this.modal.getFooter().find('[data-action="cancel"]').on('click', function() {
                            modal.hide();
                            setTimeout(function(){
                                modal.destroy();
                            }, 100);
                            // modal.destroy();
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
            
            
            // });
            
        };

         /**
         * @method getBody
         * @private
         * @return {Promise}
         */
        Newdomain.prototype.getBody = function(formdata) {
            if (typeof formdata === "undefined") {
                formdata = {};
            }
            // Get the content of the modal.
            var params = {domainid:this.args.domainid, jsonformdata: JSON.stringify(formdata)};
            return Fragment.loadFragment('local_positions', 'domain_form', this.args.contextid, params);
        
        };

        /**
         * @method handleFormSubmissionResponse
         * @private
         * @return {Promise}
         */
        Newdomain.prototype.handleFormSubmissionResponse = function() {
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
         * @return {Promise}
         */
        Newdomain.prototype.handleFormSubmissionFailure = function(data) {
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
        Newdomain.prototype.submitFormAjax = function(e) {
            // We don't want to do a real form submission.
            e.preventDefault();
     
            // Convert all the form elements values to a serialised string.
            var formData = this.modal.getRoot().find('form').serialize();
            
            // Now we can continue...
            Ajax.call([{
                methodname: 'local_positions_submit_domain_form',
                args: {contextid: this.args.contextid, jsonformdata: formData},
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
        Newdomain.prototype.submitForm = function(e) {
            e.preventDefault();
            var self = this;
            self.modal.getRoot().find('form').submit();
        };
    return{
        init: function(args){
            return new Newdomain(args);
        },
        domaintable: function(){
            // $("#all_domains_display_table").dataTable({
   //              "processing": true,
   //              "bServerSide": true,
   //              "sAjaxSource":M.cfg.wwwroot + "/local/positions/ajax.php?action=getdomainstable",
   //              "aaSorting": [],
   //              "pageLength": 10,
   //          });
   //          $("#all_domains_display_table").css('width', '100%');
            $('#all_domains_display_table').dataTable({
                "searching": false,
                //"responsive": true,
                "aaSorting": [],
                "lengthMenu": [[10, 20, 40,80,100, -1], [10,20,40, 80,100, "All"]],
                "aoColumnDefs": [{ 'bSortable': false, 'aTargets': [ 0 ] }],
                language: {
                    search: "_INPUT_",
                    searchPlaceholder: "Search",
                    "paginate": {
                        "next": ">",
                        "previous": "<"
                    }
                }       
            });
        },

        deletedomain: function(args){
            return Str.get_strings([{
                key: 'confirm'
            },
            {
                key: 'deletedomainconfirm',
                component: 'local_positions',
                param :args
            },
            {
                key: 'delete'
            }]).then(function(s) {
                ModalFactory.create({
                    title: s[0],
                    type: ModalFactory.types.SAVE_CANCEL,
                    body: s[1]
                }).done(function(modal) {
                    this.modal = modal;
                    //modal.setSaveButtonText(s[2]);
                    modal.setSaveButtonText(Str.get_string('yes_delete', 'local_positions'));


                    //For cancel button string changed//
                    var value=Str.get_string('cancel', 'local_positions');
                    var button = this.modal.getFooter().find('[data-action="cancel"]');
                    this.modal.asyncSet(value, button.text.bind(button));
                    
                    modal.getRoot().on(ModalEvents.save, function(e) {
                        e.preventDefault();
                        args.confirm = true;
                        $.ajax({
                            method: "POST",
                            dataType: "json",
                            url: M.cfg.wwwroot + "/local/positions/ajax.php?action=deletedomain&domainid="+args.domainid,
                            success: function(data){
                                window.location.href = M.cfg.wwwroot + "/local/positions/domains.php";
                            }
                        });
                    }.bind(this));
                    modal.show();
                }.bind(this));
            }.bind(this));

        },
    }
});
