// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * TODO describe module topicsconfiguration form
 *
 * @module     local_courses/contentconfiguration
 * @author     2024 Rizwana <rizwana.madire@moodle.com>
 * @copyright  Moodle India
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import DynamicForm from 'core_form/modalform';
import { get_string as getString } from 'core/str';
import ModalFactory from 'core/modal_factory';
import Ajax from 'core/ajax';
import Modal from 'core/modal';
import ModalEvents from 'core/modal_events';
import Notification from 'core/notification';
import Templates from 'core/templates';
import $ from 'jquery';

const Selectors = {
    actions: {
        topicsconfiguration: '[data-action="topicsconfiguration"]',
        groupsconfiguration: '[data-action="groupsconfiguration"]',
        groupcontentconfiguration: '[data-action="groupcontentconfiguration"]',
    },
};

export const init = () => {
    document.addEventListener('click', function(e) {
        let topicsconfiguration = e.target.closest(Selectors.actions.topicsconfiguration);
        if (topicsconfiguration) {
            e.preventDefault();
            e.stopImmediatePropagation();
            const coursename = topicsconfiguration.getAttribute('data-name');
            const topicsselected = topicsconfiguration.getAttribute('data-topicsselected');
            getString('coursetopicsconfiguration', 'local_courses', coursename).then(function(title) {
                const form = new DynamicForm({
                    formClass: 'local_courses\\form\\topicsconfiguration',
                    args: { id: topicsconfiguration.getAttribute('data-id'), courseid: topicsconfiguration.getAttribute('data-courseid') },
                    modalConfig: { title },
                    saveButtonText: getString('save'),
                    returnFocus: topicsconfiguration,
                });

               
                form.addEventListener(form.events.FORM_SUBMITTED, function() {
                    let messages = '';
                    let type = '';
                    if(e.detail.warnings !== null && e.detail.warnings != undefined) {
                        type = 'error';
                        messages = e.detail.warnings.map(warning => warning.message);

                    } else {
                        type = 'success';
                        messages = topicsselected;
                    }
                    Notification.addNotification({
                        type: type,
                        message: messages
                    });
                    setTimeout( function() { window.location.reload(); }, 1000);
                    // window.location.reload();
                });

                form.show();

                // Add classes and animate when the form is loaded
                form.addEventListener(form.events.LOADED, function() {
                    
                    bindToggleTopicCheckboxEvents(); // Ensure checkbox binding on form load
                    // Call toggleTopicCheckboxes immediately to set initial states
                    // toggleTopicCheckboxes();
                    if (form.modal) {
                        const modalRoot = form.modal.getRoot();
                        modalRoot.addClass('openLMStransition local_coursetopics');
                        modalRoot.animate({"right": "0"}, 500); // Animate to bring it into view

                        modalRoot.on(ModalEvents.hidden, () => {
                            modalRoot.animate({"right": "-85%"}, 500);
                            setTimeout(function() {
                                form.modal.destroy();
                            }, 1000);
                            form.modal.setBody('');
                        });

                    }
                });



            });
        }
    });

    document.addEventListener('click', function(e) {
        let groupsconfiguration = e.target.closest(Selectors.actions.groupsconfiguration);
        if (groupsconfiguration) {
            e.preventDefault();
            e.stopImmediatePropagation();
            const coursename = groupsconfiguration.getAttribute('data-name');
            const classesselected = groupsconfiguration.getAttribute('data-classesselected');
            const difficultylevel = groupsconfiguration.getAttribute('data-level');
            const titleparams = {coursename : coursename, difficultylevel : difficultylevel};
            getString('coursegroupsconfiguration', 'local_courses', titleparams).then(function(title) {
                const form = new DynamicForm({
                    formClass: 'local_courses\\form\\groupsconfiguration',
                    args: { id: groupsconfiguration.getAttribute('data-id'), courseid: groupsconfiguration.getAttribute('data-courseid') },
                    modalConfig: { title },
                    saveButtonText: getString('save'),
                    returnFocus: groupsconfiguration,
                });


                form.addEventListener(form.events.FORM_SUBMITTED, () => {
                    const formData = new FormData(document.querySelector('.groupsconfiguration_form'));
                    const selectedGroups = [];

                    for (const [key, value] of formData.entries()) {
                        if (value == '1' && !isNaN(key)) {
                            selectedGroups.push(parseInt(key));
                        }
                    }

                    const params = {
                        courseid: parseInt(groupsconfiguration.getAttribute('data-courseid')),
                        selectedGroups: selectedGroups
                    };

                    var promises = Ajax.call([
                        {
                            methodname: 'local_courses_groupenrollmentrequest',
                            args: params
                        }
                    ]);

                    promises[0].done(function() {
                        window.location.reload();
                    }).fail(Notification.exception);

                    let messages = '';
                    let type = '';
                    if(e.detail.warnings !== null && e.detail.warnings != undefined) {
                        type = 'error';
                        messages = e.detail.warnings.map(warning => warning.message);

                    } else {
                        type = 'success';
                        messages = classesselected;
                    }
                    Notification.addNotification({
                        type: type,
                        message: messages
                    });
                    setTimeout( function() { window.location.reload(); }, 1000);
                });
                
                form.show();

                // Add classes and animate when the form is loaded
                form.addEventListener(form.events.LOADED, function() {
                    if (form.modal) {
                        const modalRoot = form.modal.getRoot();
                        modalRoot.addClass('openLMStransition local_coursegroups');
                        modalRoot.animate({"right": "0"}, 500); // Animate to bring it into view

                        modalRoot.on(ModalEvents.hidden, () => {
                            modalRoot.animate({"right": "-85%"}, 500);
                            // setTimeout(function() {
                            //     form.modal.destroy();
                            // }, 1000);
                            form.modal.setBody('');
                        });
                    }
                });
            });
        }
    });

    document.addEventListener('click', function(e) {
        let groupcontentconfiguration = e.target.closest(Selectors.actions.groupcontentconfiguration);
        if (groupcontentconfiguration) {
            e.preventDefault();
            e.stopImmediatePropagation();
            const name = groupcontentconfiguration.getAttribute('data-name');
            const topicsselected = groupcontentconfiguration.getAttribute('data-topicsselected');
            const actionclass = groupcontentconfiguration.getAttribute('data-actionclass');

            getString('coursetopicsconfiguration', 'local_courses', name).then(function(title) {
                const form = new DynamicForm({
                    formClass: 'local_courses\\form\\groupcontentconfiguration',
                    args: { id: groupcontentconfiguration.getAttribute('data-id'), courseid: groupcontentconfiguration.getAttribute('data-courseid'), groupid: groupcontentconfiguration.getAttribute('data-groupid') },
                    modalConfig: { title },
                    saveButtonText: getString('save'),
                    returnFocus: groupcontentconfiguration,
                });

               
                form.addEventListener(form.events.FORM_SUBMITTED, function() {
                    let messages = '';
                    let type = '';
                    if(e.detail.warnings !== null && e.detail.warnings != undefined) {
                        type = 'error';
                        messages = e.detail.warnings.map(warning => warning.message);

                    } else {
                        type = 'success';
                        messages = topicsselected;
                    }
                    Notification.addNotification({
                        type: type,
                        message: messages
                    });
                    setTimeout( function() { window.location.reload(); }, 1000);
                    // window.location.reload();
                });

                form.show();

                // Add classes and animate when the form is loaded
                form.addEventListener(form.events.LOADED, function() {
                    bindToggleTopicCheckboxEvents(); // Ensure checkbox binding on form load

                     // Prepare classes
                    let classes = ['openLMStransition', 'groupcontentconfiguration'];
                    if (actionclass) {
                        classes.push(actionclass);
                    }
                    
                    if (form.modal) {
                        const modalRoot = form.modal.getRoot();
                        modalRoot.addClass(classes.join(' '));
                        modalRoot.animate({"right": "0"}, 500); // Animate to bring it into view

                        modalRoot.on(ModalEvents.hidden, () => {
                            modalRoot.animate({"right": "-85%"}, 500);
                            setTimeout(function() {
                                form.modal.destroy();
                            }, 1000);
                            form.modal.setBody('');
                        });

                    }
                });



            });
        }
    });
};


// Function to handle checkbox behavior based on parent topics
function toggleTopicCheckboxes() {
    console.log("toggleTopicCheckboxes called");
    $('.topicsconfiguration_form input[type="checkbox"]').each(function () {
        const childCheckbox = $(this);
        const parentId = childCheckbox.data('parent');
        const parentCheckbox = $(`input[name="${parentId}"]`);
        if (parentId) {
            // Check if the child checkbox should be disabled
            const shouldDisable = !parentCheckbox.is(':checked');

            if (shouldDisable) {
                childCheckbox.prop('disabled', true).prop('checked', false);
                childCheckbox.addClass('disabled-checkbox'); // Add the class when disabled
            } else {
                childCheckbox.prop('disabled', false);
                childCheckbox.removeClass('disabled-checkbox'); // Remove the class when enabled
            }
        }

        parentCheckbox.on('change', function () {
            if (!parentCheckbox.is(':checked')) {
                childCheckbox.prop('checked', false).prop('disabled', true);
                childCheckbox.addClass('disabled-checkbox'); // Add the class when disabled
            } else {
                childCheckbox.prop('disabled', false);
                childCheckbox.removeClass('disabled-checkbox'); // Remove the class when enabled
            }
        });
    });

    $('.groupcontentconfiguration_form input[type="checkbox"]').each(function () {
        const childCheckbox = $(this);
        const parentId = childCheckbox.data('parent');
        const parentCheckbox = $(`input[name="${parentId}"]`);
        if (parentId) {
            // Check if the child checkbox should be disabled
            const shouldDisable = !parentCheckbox.is(':checked');

            if (shouldDisable) {
                childCheckbox.prop('disabled', true).prop('checked', false);
                childCheckbox.addClass('disabled-checkbox'); // Add the class when disabled
            } else {
                childCheckbox.prop('disabled', false);
                childCheckbox.removeClass('disabled-checkbox'); // Remove the class when enabled
            }
        }

        parentCheckbox.on('change', function () {
            if (!parentCheckbox.is(':checked')) {
                childCheckbox.prop('checked', false).prop('disabled', true);
                childCheckbox.addClass('disabled-checkbox'); // Add the class when disabled
            } else {
                childCheckbox.prop('disabled', false);
                childCheckbox.removeClass('disabled-checkbox'); // Remove the class when enabled
            }
        });
    });
}


// Bind the checkbox change events
function bindToggleTopicCheckboxEvents() {
    // Initial call to set the state on load
    toggleTopicCheckboxes();

    // Use delegated event handling for dynamic content
    $(document).on('change', '.topicsconfiguration_form input[type="checkbox"]', function () {
        toggleTopicCheckboxes();
    });

    // Use delegated event handling for dynamic content
    $(document).on('change', '.groupcontentconfiguration_form input[type="checkbox"]', function () {
        toggleTopicCheckboxes();
    });
}

// Ensure the checkbox binding is set up when the form is loaded
$(document).ready(function () {
    bindToggleTopicCheckboxEvents(); // Call it here to ensure it sets up correctly
});


