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
 * @module     local_courses/tenantsettings
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
    },
};

export const init = () => {
    document.addEventListener('click', function(e) {
        let element = e.target.closest(Selectors.actions.topicsconfiguration);
        if (element) {
            e.preventDefault();
            e.stopImmediatePropagation();
            const coursename = element.getAttribute('data-name');
            getString('coursetopicsconfiguration', 'local_courses', coursename).then(function(title) {
                const form = new DynamicForm({
                    formClass: 'local_courses\\form\\topicsconfiguration',
                    args: { id: element.getAttribute('data-id'), courseid: element.getAttribute('data-courseid') },
                    modalConfig: { title },
                    saveButtonText: getString('save'),
                    returnFocus: element,
                });

               
                form.addEventListener(form.events.FORM_SUBMITTED, () => window.location.reload());

                form.show();

                // Add classes and animate when the form is loaded
                form.addEventListener(form.events.LOADED, function() {
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

                // Ensure modal is not null before setting events
                // if (form.modal) {
                //     const modalRoot = form.modal.getModal();
                //     if (modalRoot) {
                //         modalRoot.on(ModalEvents.hidden, function() {
                //             modalRoot.animate({"right": "-85%"}, 500);
                //             setTimeout(function() {
                //                 form.modal.destroy();
                //             }, 1000);
                //             form.modal.setBody('');
                //         });
                //     }
                // }
            });
        }
    });
};
