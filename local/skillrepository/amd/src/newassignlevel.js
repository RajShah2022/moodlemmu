/**
 * Add a create new group modal to the page.
 *
 * @module     local_location/location
 * @class      NewInstitute
 * @package    local_location
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['local_skillrepository/jquery.dataTables', 'jquery', 'core/str', 'core/modal_factory', 'core/modal_events', 'core/fragment', 'core/ajax', 'core/yui'],
        function(dataTable, $, Str, ModalFactory, ModalEvents, Fragment, Ajax, Y) {

    /**
     * Constructor
     *
     * @param {String} selector used to find triggers for the new group modal.
     * @param {int} contextid
     *
     * Each call to init gets it's own instance of this class.
     */
    var NewAssignlevel = function(args) {
        this.contextid = args.contextid;
        this.costcenterid = args.costcenterid;
        this.competencyid = args.competencyid;
        this.skillid = args.repositoryid;
        // this.positionid = args.positionid;
        // this.levelid = args.levelid;
        var self = this;
        self.init(args.selector);
    };

    /**
     * @var {Modal} modal
     * @private
     */
    NewAssignlevel.prototype.modal = null;

    /**
     * @var {int} contextid
     * @private
     */
    NewAssignlevel.prototype.contextid = -1;

    /**
     * Initialise the class.
     *
     * @param {String} selector used to find triggers for the new group modal.
     * @private
     * @return {Promise}
     */
    NewAssignlevel.prototype.init = function(args) {
        // console.log(args);
        //var triggers = $(selector);
        var self = this;
            return Str.get_string('assignlevel', 'local_skillrepository',self).then(function(title) {
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
    NewAssignlevel.prototype.getBody = function(formdata) {
        if (typeof formdata === "undefined") {
            formdata = {};
        }
        // alert(formdata);
        // Get the content of the modal.
        var params = {costcenterid:this.costcenterid, competencyid:this.competencyid, skillid:this.skillid/*, positionid:this.positionid, levelid:this.levelid*/, jsonformdata: JSON.stringify(formdata)};
        return Fragment.loadFragment('local_skillrepository', 'new_assignlevel', this.contextid, params);
    };

    /**
     * @method handleFormSubmissionResponse
     * @private
     * @return {Promise}
     */
    NewAssignlevel.prototype.handleFormSubmissionResponse = function() {
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
    NewAssignlevel.prototype.handleFormSubmissionFailure = function(data) {
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
    NewAssignlevel.prototype.submitFormAjax = function(e) {
        // We don't want to do a real form submission.
        e.preventDefault();

        // Convert all the form elements values to a serialised string.
        var formData = this.modal.getRoot().find('form').serialize();
        // alert(this.contextid);
        // Now we can continue...
        Ajax.call([{
            methodname: 'local_skillrepository_submit_assignlevel_form',
            args: {contextid: this.contextid, costcenterid:this.costcenterid, skillid:this.skillid, competencyid:this.competencyid,/* positionid:this.positionid, levelid:this.levelid,*/ jsonformdata: JSON.stringify(formData)},
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
    NewAssignlevel.prototype.submitForm = function(e) {
        e.preventDefault();
        var self = this;
        self.modal.getRoot().find('form').submit();
    };

    var dataTableshow = function(tableid){
        // alert(tableid);
        $(tableid).dataTable({
            'bPaginate': true,
            "ordering": false,
            'bFilter': true,
            "pageLength": 5,
            'bLengthChange': false,
            'language': {
                'emptyTable': 'No Records Found',
                'paginate': {
                    'previous': '<',
                    'next': '>'
                }
            },

            'bProcessing': false,
        });
    }

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
            return new NewAssignlevel(args);
        },
        load: function(){
            $(document).on('click', '.levelnameclass.assignlevel', function(){
                var element = $(this);
                if(element.hasClass('skillassigned')){
                    var existEnabled = true;
                }else{
                    var existEnabled = false;
                }
                // var siblings = $(this).parent().siblings();
                // siblings.attr('class', 'levelname nolevel');
                // siblings.children().removeClass('skillassigned').addClass('nolevel');
                var data = element.data();
                var contextid = data.contextid;
                var costcenterid = data.costcenterid;
                var competencyid = data.competencyid;
                var skillid = data.skillid;
                var positionid = data.positionid;
                var levelid = data.levelid;
                var skilllevel = data.skilllevel;
                var newclass = data.scheme;
                
                var promise = Ajax.call([{
                    methodname: 'skill_level_confirmation',
                    args: {
                        contextid:contextid,
                        costcenterid: costcenterid,
                        competencyid: competencyid,
                        skillid: skillid,
                        positionid: positionid,
                        levelid:levelid,
                        skilllevel:skilllevel,
                    },
                }]);
                // console.log(promise);
                promise[0].done(function(resp) {
                    if(existEnabled){
                        element.removeClass('skillassigned');    
                        element.parent().removeClass(newclass).addClass('nolevel');
                    }else{
                        element.addClass('skillassigned');
                        element.parent().removeClass('nolevel').addClass(newclass);
                    }
                   // location.reload();
                }).fail(function() {
                    // do something with the exception
                    alert('Error occured while processing request');
                     // window.location.reload();
                });
            });
            // $(document).on('click', '.removelevelSkill', function(){
            //     var element = $(this);
            //     var data = element.data();
            //     var costcenterid = data.costcenterid;
            //     var skillid = data.skillid;
            //     var levelid = data.levelid;
            //     var promise = Ajax.call([{
            //         methodname: 'local_skillrepository_purge_skill_level',
            //         args: {
            //             costcenterid: costcenterid,
            //             skillid: skillid,
            //             levelid:levelid
            //         },
            //     }]);
            //     promise[0].done(function(resp) {
            //        window.location.reload(); 
            //     });
            // });
            $(document).on('click', '.removelevelSkill', function(){
                var element = $(this);
                var data = element.data();
                var costcenterid = data.costcenterid;
                var skillid = data.skillid;
                var levelid = data.levelid;
                var competencyid = data.competencyid;
                return Str.get_strings([{
                        key: 'confirm'
                    },
                    {
                        key: 'purgeLevelConfirm',
                        component: 'local_skillrepository',
                        param :data
                    }
                    ]).then(function(s) {
                        ModalFactory.create({
                            title: s[0],
                            type: ModalFactory.types.SAVE_CANCEL,
                            body: s[1]
                        }).done(function(modal) {
                            this.modal = modal;
                            // modal.setSaveButtonText(s[0]);
 

                        modal.setSaveButtonText(Str.get_string('yes_delete', 'local_skillrepository'));


                        //For cancel button string changed//
                        var value=Str.get_string('cancel', 'local_skillrepository');
                        var button = this.modal.getFooter().find('[data-action="cancel"]');
                        this.modal.asyncSet(value, button.text.bind(button));

                           modal.getRoot().on(ModalEvents.save, function(e) {
                                e.preventDefault();
                                var promise = Ajax.call([{
                                    methodname: 'local_skillrepository_purge_skill_level',
                                    args: {
                                        costcenterid: costcenterid,
                                        skillid: skillid,
                                        levelid:levelid,
                                        competencyid:competencyid
                                    },
                                }]);
                                promise[0].done(function(resp) {
                                   window.location.reload(); 
                                });
                            }.bind(this));
                            modal.show();
                        }.bind(this));
                }.bind(this));
            });
            $(document).on('click', '.removeCompetencyLevel', function(){
                var element = $(this);
                var data = element.data();
                var costcenterid = data.costcenterid;
                var competencyid = data.competencyid;
                var levelid = data.levelid;
                return Str.get_strings([{
                        key: 'confirm'
                    },
                    {
                        key: 'purgeCompetencyLevelConfirm',
                        component: 'local_skillrepository',
                        param :data
                    }
                    ]).then(function(s) {
                        ModalFactory.create({
                            title: s[0],
                            type: ModalFactory.types.SAVE_CANCEL,
                            body: s[1]
                        }).done(function(modal) {
                            this.modal = modal;
                            modal.setSaveButtonText(s[0]);
                            modal.getRoot().on(ModalEvents.save, function(e) {
                                e.preventDefault();
                                var promise = Ajax.call([{
                                    methodname: 'local_skillrepository_purge_competency_level',
                                    args: {
                                        costcenterid: costcenterid,
                                        competencyid: competencyid,
                                        levelid:levelid
                                    },
                                }]);
                                promise[0].done(function(resp) {
                                   window.location.reload(); 
                                });
                            }.bind(this));
                            modal.show();
                        }.bind(this));
                }.bind(this));
            });

            $(document).on('click', '.removeLevelSkill', function(){
                var element = $(this);
                var data = element.data();
                var costcenterid = parseInt(data.costcenterid);
                var competencyid = parseInt(data.competencyid);
                var levelid = parseInt(data.levelid);
                var skillid = parseInt(data.skillid);
                return Str.get_strings([{
                        key: 'confirm'
                    },
                    {
                        key: 'purgeLevelSkillConfirm',
                        component: 'local_skillrepository',
                        param :data
                    }
                    ]).then(function(s) {
                        ModalFactory.create({
                            title: s[0],
                            type: ModalFactory.types.SAVE_CANCEL,
                            body: s[1]
                        }).done(function(modal) {
                            this.modal = modal;
                            // modal.setSaveButtonText(s[0]);
 

                        modal.setSaveButtonText(Str.get_string('yes_delete', 'local_skillrepository'));


                        //For cancel button string changed//
                        var value=Str.get_string('cancel', 'local_skillrepository');
                        var button = this.modal.getFooter().find('[data-action="cancel"]');
                        this.modal.asyncSet(value, button.text.bind(button));

                           modal.getRoot().on(ModalEvents.save, function(e) {
                                e.preventDefault();
                                var promise = Ajax.call([{
                                    methodname: 'local_skillrepository_purge_level_skill',
                                    args: {
                                        costcenterid: costcenterid,
                                        competencyid: competencyid,
                                        levelid: levelid,
                                        skillid: skillid
                                    },
                                }]);
                                promise[0].done(function(resp) {
                                   window.location.reload(); 
                                });
                            }.bind(this));
                            modal.show();
                        }.bind(this));
                }.bind(this));
            });
            $(document).on('click', '.removeSkillCourse', function(){
                var element = $(this);
                data = element.data();
                var courseid = data.courseid;
                var skillid = data.skillid;
                return Str.get_strings([{
                        key: 'confirm'
                    },
                    {
                        key: 'purgeSkillCourseConfirm',
                        component: 'local_skillrepository',
                        param :data
                    }
                    ]).then(function(s) {
                        ModalFactory.create({
                            title: s[0],
                            type: ModalFactory.types.SAVE_CANCEL,
                            body: s[1]
                        }).done(function(modal) {
                            this.modal = modal;
                            modal.setSaveButtonText(s[0]);
                            modal.getRoot().on(ModalEvents.save, function(e) {
                                e.preventDefault();
                                var promise = Ajax.call([{
                                    methodname: 'local_skillrepository_purge_skill_course',
                                    args: {
                                        courseid: courseid,
                                        skillid: skillid
                                    },
                                }]);
                                promise[0].done(function(resp) {
                                   window.location.reload(); 
                                });
                            }.bind(this));
                            modal.show(); 
                        }.bind(this));
                }.bind(this));
            });

        },
        getDomains: function() {
                // alert('orgID');
            $(document).on('change', '#id_costcenterid', function(){
                customstrings = Str.get_strings(
                [{
                    key: 'selectdomain',
                    component: 'local_skillrepository'
                }]);
                return customstrings.then(function(strings) {
                var orgID = $(this).val();
                // alert(orgID);
                if(orgID){
                    var promise = Ajax.call([{
                        methodname: 'local_org_domains',
                        args: {
                            orgid: orgID,
                        },
                    }]);
                    promise[0].done(function(resp) {
                        var template =  '<option value=null>'+strings[0]+'</option>'; 
                        $.each(JSON.parse(resp), function( index, value) {
                            template += '<option value = ' + index + ' >' +value + '</option>';
                        });
                        $('#id_domain').html(template);
                    }).fail(function() {
                        // do something with the exception
                        alert('Error occured while processing request');
                         window.location.reload();
                    });
                } else {
                    var template =  '<option value=\'\'>'+strings[0]+'</option>'; 
                    $('#id_domain').html(template);
                }
            }.bind(this));
           });
        },
        getLevels: function(args) {
            $(document).on('change', '#id_leveltype', function(){
                customstrings = Str.get_strings(
                [{
                    key: 'selectlevel',
                    component: 'local_skillrepository'
                }]);
                return customstrings.then(function(strings) {
                var costcenterid = $('#id_costcenterid').val();
                if(costcenterid > 0) {
                    costcenterid = costcenterid;
                } else {
                    costcenterid = $('input[name=costcenterid]').val();
                }
                var leveltype = $(this).val();
                if(costcenterid != 0 && leveltype !=0){
                    var promise = Ajax.call([{
                        methodname: 'get_levels',
                        args: {
                            costcenterid: costcenterid,
                            leveltype: leveltype,
                        },
                    }]);
                    promise[0].done(function(resp) { 
                        var template =  '<option value=null>'+strings[0]+'</option>'; 
                        $.each(JSON.parse(resp), function( index, value) {
                            template += '<option value = ' + index + ' >' +value + '</option>';
                        });
                        $('#id_levelid').html(template);
                    }).fail(function() {
                        // do something with the exception
                        alert('Error occured while processing request');
                         window.location.reload();
                    });
                } else {
                    var template =  '<option value=\'\'>'+strings[0]+'</option>'; 
                    $('#id_levelid').html(template);
                }
               }.bind(this));
            });
        },
        displaySkillLevels: function(args){
            var params = { skillid: args.skillid, costcenterid: args.costcenterid};
            var returndata =  Fragment.loadFragment('local_skillrepository', 'skill_level_display', 1, params);
            ModalFactory.create({
                title: Str.get_string('skilllevelsinfo', 'local_skillrepository', args.skillname),
                body: returndata
            }).done(function(modal) {
                // Do what you want with your new modal.
                modal.show();
                modal.getRoot().on(ModalEvents.bodyRendered, function() {
                    dataTableshow('#skilllevel_info');
                }.bind(this));
                modal.setLarge();
                modal.getRoot().on(ModalEvents.hidden, function() {
                    modal.setBody('');
                }.bind(this));
                modal.getRoot().find('[data-action="hide"]').on('click', function() {
                    modal.hide();
                    setTimeout(function(){
                         modal.destroy();
                    }, 500);
                });
            });
        },
        displaySkillCourses: function(args){
            var params = { skillid: args.skillid, costcenterid: args.costcenterid};
            var returndata =  Fragment.loadFragment('local_skillrepository', 'skill_courses_display', 1, params);
            ModalFactory.create({
                title: Str.get_string('skillcoursesinfo', 'local_skillrepository', args.skillname),
                body: returndata
            }).done(function(modal) {
                // Do what you want with your new modal.
                modal.show();
                modal.getRoot().on(ModalEvents.bodyRendered, function() {
                     dataTableshow('#skillcourse_info');
                }.bind(this));
                modal.setLarge();
                modal.getRoot().on(ModalEvents.hidden, function() {
                    modal.setBody('');
                }.bind(this));
                modal.getRoot().find('[data-action="hide"]').on('click', function() {
                    modal.hide();
                    setTimeout(function(){
                         modal.destroy();
                    }, 500);
                });
            });
        },
        getSkillLevels: function(args, event) {
            // $(document).on('click', '.levelnameclass', function(args){
                console.log(event);
                var contextid = 1;
                var costcenterid = args.costcenterid;
                var competencyid = args.competencyid;
                var skillid = args.skillid;
                var positionid = args.positionid;
                var levelid = args.levelid;
                var skilllevel = args.skilllevel;
                if(costcenterid != 0 && competencyid != 0 && skillid != 0 && positionid != 0 && levelid != 0 && skilllevel != 0){
                    var promise = Ajax.call([{
                        methodname: 'skill_level_confirmation',
                        args: {
                            contextid:contextid,
                            costcenterid: costcenterid,
                            competencyid: competencyid,
                            skillid: skillid,
                            positionid: positionid,
                            levelid:levelid,
                            skilllevel:skilllevel,
                        },
                    }]);
                    // console.log(promise);
                    promise[0].done(function(resp) {

                       // location.reload();
                    }).fail(function() {
                        // do something with the exception
                        alert('Error occured while processing request');
                         window.location.reload();
                    });
                } else {
                    var template =  '<option value=\'\'>--Select Level--</option>';
                    $('#id_levelid').html(template);
                }
            // });
        },

    };
});
