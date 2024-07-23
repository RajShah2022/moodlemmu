define([
    'local_courses/jquery.dataTables',
    'jquery','core/str','core/modal_factory', 'core/ajax'
], function(DataTable, $,Str,ModalFactory, Ajax){
    return courses = {
        load : function() {
        },
        usersdatatable : function(args) {
            console.log(args);
            params = [];
            params.action = args.action;
            params.courseid = args.courseid;
            params.moduletype = args.moduletype;
			Str.get_strings([{
                key: 'search',
                component: 'local_courses'
            },
            {
                key: 'no_users_enrolled',
                component: 'local_courses'
            }]).then(function(s) {
                var oTable = $('#course_users').dataTable({
                    'bInfo': false,
                    'processing': true,
                    'serverSide': true,
                    'ordering': false,
                    'ajax': {
                        "type": "POST",
                        "url": M.cfg.wwwroot + '/local/courses/ajax.php',
                        "data": params
                    },
                    "bLengthChange": false,
                    "language": {
                        "paginate": {
                            "next": ">",
                            "previous": "<"
                        },
                        'processing': '<img src='+M.cfg.wwwroot + '/local/ajax-loader.svg>'
                    },
                    "oLanguage": {
                      "sSearch" : s[0],
        			  "sZeroRecords": s[1]
                    },
                });
			});
        },
        deleteuser: function(element) {
            var enrollmentid = element.userid;
            // console.log(element);
            return Str.get_strings([{
                key: 'deleteuser',
                component: 'local_courses'
            }, {
                key: 'confirmdelete',
                component: 'local_courses'
            }]).then(function(s) {
                ModalFactory.create({
                    title: s[0],
                    type: ModalFactory.types.DEFAULT,
                    body: s[1],
                    footer: '<button type="button" class="btn btn-primary" data-action="save">'+M.util.get_string("yes", "moodle")+'</button>&nbsp;' +
                    '<button type="button" class="btn btn-secondary" data-action="cancel">'+M.util.get_string("no", "moodle")+'</button>'
                }).done(function(modal) {
                    this.modal = modal;
                    modal.getRoot().find('[data-action="save"]').on('click', function() {
                        window.location.href ='enrolledusers.php?id='+element.id+'&ue='+element.userid+'&confirm=1&sesskey=' + M.cfg.sesskey;
                    }.bind(this));
                    modal.getFooter().find('[data-action="cancel"]').on('click', function() {
                        modal.setBody('');
                        modal.hide();
                    });
                    modal.show();
                }.bind(this));
            }.bind(this));
        },
           regeneratecertificate: function(element) {
            var enrollmentid = element.issueid;
            // console.log(element);
            return Str.get_strings([{
                key: 'regeneratecertificate',
                component: 'local_courses'
            }, {
                key: 'confirmregenerate',
                component: 'local_courses'
            }]).then(function(s) {
                ModalFactory.create({
                    title: s[0],
                    type: ModalFactory.types.DEFAULT,
                    body: s[1],
                    footer: '<button type="button" class="btn btn-primary" data-action="save">'+M.util.get_string("yes", "moodle")+'</button>&nbsp;' +
                    '<button type="button" class="btn btn-secondary" data-action="cancel">'+M.util.get_string("no", "moodle")+'</button>'
                }).done(function(modal) {
                    this.modal = modal;
                    modal.getRoot().find('[data-action="save"]').on('click', function() {
                         element.confirm = true;
                         var params ={};
                         params.id= element.issueid;
                        var promise = Ajax.call([{
                            methodname: 'tool_certificate_regenerate_issue_file',
                            args:params
                        }]);
                        promise[0].done(function() {
                            window.location.href = window.location.href;
                        }).fail(function(ex) {
                            // do something with the exception
                             console.log(ex);
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
        enrollUsers: function (args) {
            // console.log(args);return;
            return Str.get_strings([{
                key: 'confirm'
            },
            {
                key: 'enrolluserstocourse',
                component: 'local_courses',
                param: args
            }]).then(function (s) {
                ModalFactory.create({
                    title: s[0],
                    type: ModalFactory.types.DEFAULT,
                    body: s[1],
                    footer: '<button type="button" class="btn btn-primary" data-action="save">Yes</button>&nbsp;' + 
                    '<button type="button" class="btn btn-secondary" data-action="cancel">No</button>'
                }).done(function (modal) {
                    this.modal = modal;

                    modal.getRoot().find('[data-action="save"]').on('click', function () {
                        window.location.href = M.cfg.wwwroot+ '/local/courses/courseautoenrol.php?confirmation=1&id='+args.courseid;
                    }.bind(this));
                    modal.getFooter().find('[data-action="cancel"]').on('click', function () {
                        modal.setBody('');
                        modal.hide();
                    });
                    modal.show();
                }.bind(this));
            }.bind(this));
        },
    };
    
});
