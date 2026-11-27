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


const FORM_SELECTOR = '.groupcontentconfiguration_form';

const allBoxes = (form) => Array.from(form.querySelectorAll('input[type="checkbox"]'));
const boxByName = (form, name) => allBoxes(form).find((box) => box.name === name);

// Every checkbox below a section, however deep: child sections and their activities.
const descendantsOf = (form, sectionname) => {
    const boxes = allBoxes(form);
    const found = [];
    const queue = [sectionname];
    while (queue.length) {
        const current = queue.shift();
        boxes.forEach((box) => {
            if (box.dataset.parent === current) {
                found.push(box);
                if (box.dataset.kind === 'section') {
                    queue.push(box.name);
                }
            }
        });
    }
    return found;
};

// A child can only be on while its parents are, so turning a section on turns its ancestors on too.
const ancestorsOf = (form, box) => {
    const found = [];
    let parent = box.dataset.parent ? boxByName(form, box.dataset.parent) : null;
    while (parent) {
        found.push(parent);
        parent = parent.dataset.parent ? boxByName(form, parent.dataset.parent) : null;
    }
    return found;
};

// Tick the boxes first, then fire one change event so the parent/child cascade
// sees every parent already on and leaves nothing disabled (disabled boxes are not submitted).
const tickBoxes = (boxes) => {
    if (!boxes.length) {
        return;
    }
    boxes.forEach((box) => {
        box.checked = true;
        box.disabled = false;
        box.classList.remove('disabled-checkbox');
    });
    $(boxes[0]).trigger('change');
};

const decorateForm = (form) => {
    form.dataset.gccDone = '1';
    const boxes = allBoxes(form);
    const scopes = new Map(); // Button => the section's own box plus everything below it.

    const bar = form.querySelector('[data-action="enable-all-toggles"]');
    if (bar && bar.parentElement) {
        bar.parentElement.classList.add('gcc-bar');
    }

    boxes.forEach((box) => {
        const row = box.closest('.fitem');
        if (!row) {
            return;
        }
        const issection = box.dataset.kind === 'section';
        row.classList.add('gcc-row', issection ? 'gcc-section' : 'gcc-activity');
        row.style.setProperty('--gcc-depth', box.dataset.depth || 0);
        if (!issection) {
            return;
        }

        const below = descendantsOf(form, box.name);
        if (!below.length) {
            return;
        }
        const items = below.filter((b) => b.dataset.kind === 'activity').length;
        const holder = row.querySelector('.form-check');
        if (!holder) {
            return;
        }
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'btn btn-sm btn-outline-primary gcc-enable-below';
        button.dataset.action = 'enable-below';
        button.dataset.target = box.name;
        button.textContent = 'Enable all';
        button.title = 'Turns on this topic, its sub-topics and all ' + items + ' quizzes and activities inside';
        holder.appendChild(button);
        scopes.set(button, [box, ...below]);
    });

    // Nothing left to enable under a section: hide its button. visibility keeps its space, so a
    // second click lands on empty space instead of on whatever would slide under the cursor.
    const refreshButtons = () => {
        scopes.forEach((boxes, button) => {
            button.style.visibility = boxes.every((b) => b.checked) ? 'hidden' : '';
        });
    };
    refreshButtons();
    $(form).on('change', refreshButtons);
};

// Saving deletes every saved toggle that is not ticked, and the server then hides that content.
// One stray click on a parent switch unticks everything under it, so confirm before anything turns off.
// defaultChecked is the state the server rendered, i.e. what is saved now.
const confirmTurningOff = (event, modalform, classname) => {
    const form = modalform.getFormNode();
    if (!form || form.dataset.gccConfirmed === '1') {
        return;
    }
    const off = allBoxes(form).filter((box) => box.defaultChecked && (!box.checked || box.disabled));
    if (!off.length) {
        return;
    }
    event.preventDefault();
    const topics = off.filter((box) => box.dataset.kind === 'section').length;
    const items = off.length - topics;
    Notification.saveCancel(
        'Turn off content?',
        'Saving will turn off <b>' + topics + '</b> topic(s) and <b>' + items + '</b> quiz(zes)/activities for <b>' +
            $('<div>').text(classname || 'this class').html() + '</b> that are on now. ' +
            'Students in this class will lose access to them.',
        'Turn off and save',
        () => {
            form.dataset.gccConfirmed = '1';
            modalform.submitFormAjax();
        }
    );
};

const initContentConfigurationForm = () => {
    document.addEventListener('click', function(e) {
        const all = e.target.closest('[data-action="enable-all-toggles"]');
        const below = e.target.closest('[data-action="enable-below"]');
        const trigger = all || below;
        if (!trigger) {
            return;
        }
        e.preventDefault();
        const form = trigger.closest(FORM_SELECTOR);
        if (!form) {
            return;
        }
        if (all) {
            tickBoxes(allBoxes(form));
            return;
        }
        const section = boxByName(form, below.dataset.target);
        if (section) {
            tickBoxes([...ancestorsOf(form, section), section, ...descendantsOf(form, section.name)]);
        }
    });

    // The form is rendered into a modal after the page loads, so decorate it when it appears.
    const decorateNew = () => {
        document.querySelectorAll(FORM_SELECTOR + ':not([data-gcc-done])').forEach(decorateForm);
    };
    new MutationObserver(decorateNew).observe(document.body, {childList: true, subtree: true});
    decorateNew();
};

export const init = () => {
    initContentConfigurationForm();

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

                form.addEventListener(form.events.SUBMIT_BUTTON_PRESSED, (event) => confirmTurningOff(event, form, name));

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


