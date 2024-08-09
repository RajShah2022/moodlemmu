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
import Templates from 'core/templates';
import $ from 'jquery';

const Selectors = {
    actions: {
        topicsconfiguration: '[data-action="topicsconfiguration"]',
        groupsconfiguration: '[data-action="groupsconfiguration"]',
    },
};

export const init = () => {
    document.addEventListener('click', function(e) {
        let topicsconfiguration = e.target.closest(Selectors.actions.topicsconfiguration);
        if (topicsconfiguration) {
            e.preventDefault();
            e.stopImmediatePropagation();
            const coursename = topicsconfiguration.getAttribute('data-name');
            getString('coursetopicsconfiguration', 'local_courses', coursename).then(function(title) {
                const form = new DynamicForm({
                    formClass: 'local_courses\\form\\topicsconfiguration',
                    args: { id: topicsconfiguration.getAttribute('data-id'), courseid: topicsconfiguration.getAttribute('data-courseid') },
                    modalConfig: { title },
                    saveButtonText: getString('save'),
                    returnFocus: topicsconfiguration,
                });

               
                form.addEventListener(form.events.FORM_SUBMITTED, () => window.location.reload());

                form.show();

                // Add classes and animate when the form is loaded
                form.addEventListener(form.events.LOADED, function() {
                    
                    // bindToggleTopicCheckboxEvents(); // Ensure checkbox binding on form load
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

               
                // form.addEventListener(form.events.FORM_SUBMITTED, () => {
                //      var params = {};
                //         params.courseid = groupsconfiguration.getAttribute('data-courseid');
                //     var promises = Ajax.call([
                //         {
                //             methodname: 'local_courses_groupenrollmentrequest',
                //             args: params
                //         }
                //     ]);
                //     promises[0].done(function() {
                //         window.location.reload();
                //     }).fail(Notification.exception);
                // });

                // form.addEventListener(form.events.FORM_SUBMITTED, () => {
                //     // Access the form element directly from the DOM
                //     const modalRoot = form.modal.getRoot();
                //     const formElement = modalRoot.find('form')[0];

                //     if (formElement) {
                //         const formData = new FormData(formElement);
                //         const selectedGroups = [];

                //         for (const [key, value] of formData.entries()) {
                //             if (value == 1 && !isNaN(key)) {
                //                 // Ensure the key is numeric before adding it to selectedGroups
                //                 selectedGroups.push(parseInt(key));
                //             }
                //         }

                //         var params = {
                //             courseid: parseInt(groupsconfiguration.getAttribute('data-courseid')),
                //             selectedGroups: selectedGroups
                //         };

                //         var promises = Ajax.call([
                //             {
                //                 methodname: 'local_courses_groupenrollmentrequest',
                //                 args: params
                //             }
                //         ]);
                //         promises[0].done(function() {
                //             window.location.reload();
                //         }).fail(Notification.exception);
                //     }
                // });

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
        
        if (parentId) {
            const parentCheckbox = $(`input[name="${parentId}"]`);
            // console.log(`Checking child checkbox: ${childCheckbox.attr('name')}, Parent: ${parentId}`);
            console.log(`Parent Checkbox (ID: ${parentId}) is checked: ${parentCheckbox.is(':checked')}`);
            // Check if the child checkbox should be disabled
            const shouldDisable = !parentCheckbox.is(':checked');

            if (shouldDisable) {
                console.log(`Disabling child checkbox: ${childCheckbox.attr('name')}`);
                childCheckbox.prop('disabled', true).prop('checked', false);
            } else {
                console.log(`Enabling child checkbox: ${childCheckbox.attr('name')}`);
                childCheckbox.prop('disabled', false);
            }
        }
    });
}

// Bind the checkbox change events
function bindToggleTopicCheckboxEvents() {
    // Initial call to set the state on load
    toggleTopicCheckboxes();

    // Use delegated event handling for dynamic content
    $(document).on('change', '.topicsconfiguration_form input[type="checkbox"]', function () {
        const changedCheckbox = $(this);
        console.log(`Checkbox ${changedCheckbox.attr('name')} changed, checked: ${changedCheckbox.is(':checked')}`);
        const isParent = !changedCheckbox.data('parent');
        const parentId = isParent ? changedCheckbox.attr('name') : changedCheckbox.data('parent');
        const childCheckboxes = $(`input[data-parent="${parentId}"]`);

        console.log(`Checkbox changed: ${changedCheckbox.attr('name')}, Is parent: ${isParent}`);

        // If a parent checkbox changed, update its child checkboxes
        if (isParent) {
            const isChecked = changedCheckbox.is(':checked');
            childCheckboxes.each(function () {
                const childCheckbox = $(this);
                if (isChecked) {
                    console.log(`Enabling child checkbox: ${childCheckbox.attr('name')}`);
                    childCheckbox.prop('disabled', false);
                } else {
                    console.log(`Disabling child checkbox: ${childCheckbox.attr('name')}`);
                    childCheckbox.prop('disabled', true).prop('checked', false);
                }
            });
        } else {
            // If a child checkbox changed, ensure it's only enabled if its parent is checked
            console.log("Calling toggleTopicCheckboxes from else block");
            toggleTopicCheckboxes();
        }
    });
}

// Ensure the checkbox binding is set up when the form is loaded
$(document).ready(function () {
    // bindToggleTopicCheckboxEvents(); // Call it here to ensure it sets up correctly
});


