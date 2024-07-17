/**
 * Add a create new group modal to the page.
 *
 * @module     local_location/location
 * @class      NewInstitute
 * @package    local_location
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery', 'core/str', 'core/modal_factory', 'core/modal_events', 'core/fragment', 'core/ajax', 'core/yui'],
        function($, Str, ModalFactory, ModalEvents, Fragment, Ajax, Y) {

    /**
     * Constructor
     *
     * @param {String} selector used to find triggers for the new group modal.
     * @param {int} contextid
     *
     * Each call to init gets it's own instance of this class.
     */
    var NewAssigncourse = function(args) {
        this.contextid = args.contextid;
        this.skillid = args.repositoryid;
        this.levelid = args.levelid;
        this.costcenterid = args.org_id;
        this.competencyid = args.competencyid;
        var self = this;
        self.init(args.selector);
    };

    /**
     * @var {Modal} modal
     * @private
     */
    NewAssigncourse.prototype.modal = null;

    /**
     * @var {int} contextid
     * @private
     */
    NewAssigncourse.prototype.contextid = -1;

    /**
     * Initialise the class.
     *
     * @param {String} selector used to find triggers for the new group modal.
     * @private
     * @return {Promise}
     */
    NewAssigncourse.prototype.init = function(args) {
        // console.log(args);
        //var triggers = $(selector);
        var self = this;
            return Str.get_string('assigncourse', 'local_skillrepository',self).then(function(title) {
                // Create the modal.
                return ModalFactory.create({
                    type: ModalFactory.types.SAVE_CANCEL,
                    title: title,
                    body: self.getBody()
                });
            }.bind(self)).then(function(modal) {

                // Keep a reference to the modal.
                self.modal = modal;
                // self.modal.show();
                // Forms are big, we want a big modal.
                self.modal.setLarge();
                // this.modal.getRoot().addClass('openLMStransition');

                // We want to reset the form every time it is opened.
                this.modal.getRoot().on(ModalEvents.hidden, function() {
                    this.modal.getRoot().animate({"right":"-85%"}, 500);
                    setTimeout(function(){
                        modal.destroy();
                    }, 1000);
                }.bind(this));

                // We want to hide the submit buttons every time it is opened.
                self.modal.getRoot().on(ModalEvents.shown, function() {
                    self.modal.getRoot().append('<style>[data-fieldtype=submit] { display: none ! important; }</style>');
                    this.modal.getFooter().find('[data-action="cancel"]').on('click', function() {
                        modal.hide();
                        setTimeout(function(){
                            modal.destroy();
                        }, 1000);
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
    NewAssigncourse.prototype.getBody = function(formdata) {
        if (typeof formdata === "undefined") {
            formdata = {};
        }
        // alert(formdata);
        // Get the content of the modal.
        var params = {skillid:this.skillid, costcenterid:this.costcenterid, levelid: this.levelid, competencyid: this.competencyid, jsonformdata: JSON.stringify(formdata)};
        return Fragment.loadFragment('local_skillrepository', 'new_assigncourse', this.contextid, params);
    };

    /**
     * @method handleFormSubmissionResponse
     * @private
     * @return {Promise}
     */
    NewAssigncourse.prototype.handleFormSubmissionResponse = function() {
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
    NewAssigncourse.prototype.handleFormSubmissionFailure = function(data) {
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
    NewAssigncourse.prototype.submitFormAjax = function(e) {
        // We don't want to do a real form submission.
        e.preventDefault();

        // Convert all the form elements values to a serialised string.
        var formData = this.modal.getRoot().find('form').serialize();
        // alert(this.contextid);
        // Now we can continue...
        Ajax.call([{
            methodname: 'local_skillrepository_submit_assigncourse_form',
            args: {contextid: this.contextid, skillid:this.skillid, costcenterid:this.costcenterid, jsonformdata: JSON.stringify(formData)},
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
    NewAssigncourse.prototype.submitForm = function(e) {
        e.preventDefault();
        var self = this;
        self.modal.getRoot().find('form').submit();
    };

    return /** @alias module:local_location/newlocation */ {
        // Public variables and functions.
        /**
         * Attach event listeners to initialise this module.
         *
         * @method init
         * @param {string} selector The CSS selector used to find nodes that will trigger this module.
         * @param {int} contextid The contextid for the course.
         * @return {Promise}
         */
        init: function(args) {

            // alert(args.contextid);
            return new NewAssigncourse(args);
        },
        load: function(){

        },
        getCourselist: function(args) {
            // modal to show the courses in a category
            element = '.course_count_popup';
            if(!$(element).hasClass('clicked')){
                $(element).addClass('clicked');
                var params = { skillid: args.skillid, costcenterid: args.costcenterid, levelid: args.levelid, competencyid: args.competencyid};
                var returndata =  Fragment.loadFragment('local_skillrepository', 'skill_levelcourse_display', args.contextid, params);

                ModalFactory.create({
                    title: Str.get_string('coursepopup', 'local_skillrepository', args.categoryname),
                    body: returndata
                }).done(function(modal) {
                    // Do what you want with your new modal.
                    modal.show();
                    modal.setLarge();
                    modal.getRoot().on(ModalEvents.hidden, function() {
                        modal.setBody('');
                    }.bind(this));
                    modal.getRoot().find('[data-action="hide"]').on('click', function() {
                        $(element).removeClass('clicked');
                        modal.hide();
                        setTimeout(function(){
                             modal.destroy();
                        }, 500);
                    });
                });
            }
        },
        getCompetencylist: function(args) {
            // modal to show the courses in a category
            element = '.competency_count_popup';
            if(!$(element).hasClass('clicked')){
                $(element).addClass('clicked');
                var params = { courseid: args.courseid};
                var returndata =  Fragment.loadFragment('local_skillrepository', 'competency_course_display', args.contextid, params);

                ModalFactory.create({
                    title: Str.get_string('competencies', 'local_skillrepository'),
                    body: returndata
                }).done(function(modal) {
                    // Do what you want with your new modal.
                    modal.show();
                    modal.setLarge();
                    modal.getRoot().on(ModalEvents.hidden, function() {
                        modal.setBody('');
                    }.bind(this));
                    modal.getRoot().find('[data-action="hide"]').on('click', function() {
                        $(element).removeClass('clicked');
                        modal.hide();
                        setTimeout(function(){
                             modal.destroy();
                        }, 500);
                    });
                });
            }
        },
    };
});
