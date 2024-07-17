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
 * option selector module.
 *
 * @module     tool_lp/form-option-selector
 * @class      form-option-selector
 * @package    tool_lp
 * @copyright  2015 Frédéric Massart - FMCorz.net
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery', 'core/ajax', 'core/templates'], function($, Ajax, Templates) {

    return /** @alias module:tool_lp/form-option-selector */ {

        processResults: function(selector, results) {
            var options = [];
            $.each(results, function(index, option) {
                options.push({
                    value: option.id,
                    label: option._label
                });
            });
            return options;
        },

        transport: function(selector, query, success, failure) {
            var promise;
            var contextid = 1;
            action = $(selector).data('action');
            formoptions = $(selector).data('options');
            var newselector = selector.replace('#', '#fitem_'); 
            formoptions.selected = $(newselector + ' .form-autocomplete-selection [role=listitem]').data('value');
            if(action == 'position_domain_selector'){

                if(formoptions.userorganization == null)
                {
                    formoptions.costcenterid = $('[data-class="' + $(selector).data('parentclass') + '"]').val();
                }
                if(!formoptions.costcenterid){
                formoptions.costcenterid = $("[data-class='" + formoptions.organizationselect + "']").val();
                if(formoptions.costcenterid == undefined || formoptions.costcenterid == ''){
                    var organizationfield = '.'+formoptions.organizationselect;
                    formoptions.costcenterid = $(organizationfield + '.form-autocomplete-selection [role=listitem]').data('value');
                    if(formoptions.costcenterid == undefined || formoptions.costcenterid == ''){
                        formoptions.costcenterid = formoptions.userorganization;
                    }
                }
                }
            }else if(action == 'position_parent_selector'){
                if(!formoptions.costcenterid){
                formoptions.costcenterid = $("[data-class='" + formoptions.organizationselect + "']").val();
                if(formoptions.costcenterid == undefined || formoptions.costcenterid == ''){
                    var organizationfield = '.'+formoptions.organizationselect;
                    formoptions.costcenterid = $(organizationfield + '.form-autocomplete-selection [role=listitem]').data('value');
                    if(formoptions.costcenterid == undefined || formoptions.costcenterid == ''){
                        formoptions.costcenterid = formoptions.userorganization;
                    }
                }
                }
                formoptions.domain = $("[data-class='" + formoptions.domainselect + "']").val();
                formoptions.domain = $("#domainselect").val();

                if(formoptions.domain == undefined || formoptions.domain == ''){
                    var domainfield = '.'+formoptions.domainselect;
                    formoptions.domain = $(domainfield + '.form-autocomplete-selection [role=listitem]').data('value');
                    if(formoptions.domain == undefined || formoptions.domain == 0){
                        formoptions.domain = 0;
                    }
                }
            }
            else if(action == 'skill_selector_action')
            {
                formoptions.competencyid = $('#id_competencyid').val();
            }
            else if(action == 'level_selector_action')
            {
                formoptions.competencyid = $('#id_competencyid').val();
                formoptions.skillid = $('#id_skillid').val();
            }
            formoptions = JSON.stringify(formoptions);

            promise = Ajax.call([{
                methodname: 'local_positions_form_option_selector',
                args: {
                    query: query,
                    context: {contextid: contextid},
                    action: action,
                    options: formoptions
                }
            }]);

            promise[0].then(function(results) {
                results = $.parseJSON(results);
                var promises = [],
                    i = 0;
                // Render the label.
                $.each(results, function(index, option) {
                    promises.push(Templates.render('local_costcenter/form-option-selector-suggestion', option));
                });
                // Apply the label to the results.
                return $.when.apply($.when, promises).then(function() {
                    var args = arguments;
                    $.each(results, function(index, option) {
                        option._label = args[i];
                        i++;
                    });
                    success(results);
                    return;
                });

            }).catch(failure);
        }

    };

});
