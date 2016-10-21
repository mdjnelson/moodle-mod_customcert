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
 * AMD module used when rearranging a custom certificate.
 *
 * @module     mod_customcert/rearrange-area
 * @package    mod_customcert
 * @copyright  2016 Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery', 'core/yui', 'core/fragment', 'mod_customcert/dialogue', 'core/notification',
        'core/str', 'core/templates', 'core/ajax'],
       function($, Y, fragment, Dialogue, notification, str, template, ajax) {

    /**
     * RearrangeArea class.
     *
     * @param {String} selector The rearrange PDF selector
     */
    var RearrangeArea = function(selector) {
        this._node = $(selector);
        this._setEvents();
    };

    RearrangeArea.prototype.CUSTOMCERT_REF_POINT_TOPLEFT = 0;
    RearrangeArea.prototype.CUSTOMCERT_REF_POINT_TOPCENTER = 1;
    RearrangeArea.prototype.CUSTOMCERT_REF_POINT_TOPRIGHT = 2;

    RearrangeArea.prototype._setEvents = function() {
        this._node.on('click', '.element', this._editElement.bind(this));
    };

    RearrangeArea.prototype._editElement = function(event) {
        var elementid = event.currentTarget.id.substr(8);
        var contextid = this._node.attr('data-contextid');
        var params = {
            'elementid' : elementid
        };

        fragment.loadFragment('mod_customcert', 'editelement', contextid, params).done(function(html, js) {
            str.get_string('editelement', 'mod_customcert').done(function(title) {
                Y.use('moodle-core-formchangechecker', function () {
                    new Dialogue(
                        title,
                        '<div id=\'elementcontent\'></div>',
                        this._editElementDialogueConfig.bind(this, elementid, html, js),
                        undefined,
                        true
                    );
                }.bind(this));
            }.bind(this));
        }.bind(this)).fail(notification.exception);
    };

    RearrangeArea.prototype._editElementDialogueConfig = function(elementid, html, js, popup) {
        // Place the content in the dialogue.
        template.replaceNode('#elementcontent', html, js);

        // Add events for when we save, close and cancel the page.
        var body = $(popup.getContent());
        body.on('click', '#id_submitbutton', function(e) {
            // Do not want to ask the user if they wish to stay on page after saving.
            M.core_formchangechecker.reset_form_dirty_state();
            // Save the data.
            this._saveElement(elementid).then(function() {
                // Update the DOM to reflect the adjusted value.
                this._getElementHTML(elementid).done(function(html) {
                    var elementNode = this._node.find('#element-' + elementid);
                    var refpoint = $('#id_refpoint').val();
                    var refpointClass = '';
                    if (refpoint == this.CUSTOMCERT_REF_POINT_TOPLEFT) {
                        refpointClass = 'refpoint-left';
                    } else if (refpoint == this.CUSTOMCERT_REF_POINT_TOPCENTER) {
                        refpointClass = 'refpoint-center';
                    } else if (refpoint == this.CUSTOMCERT_REF_POINT_TOPRIGHT) {
                        refpointClass = 'refpoint-right';
                    }
                    elementNode.empty().append(html);
                    // Update the ref point.
                    elementNode.removeClass();
                    elementNode.addClass('element ' + refpointClass);
                    // Move the element if we need to.
                    this._setPosition(elementid, refpoint);
                    popup.close();
                }.bind(this));
            }.bind(this));
            e.preventDefault();
        }.bind(this));

        body.on('click', '#id_cancel', function(e) {
            popup.close();
            e.preventDefault();
        }.bind(this));
    };

    RearrangeArea.prototype._setPosition = function(elementid, refpoint) {
        var element = Y.one('#element-' + elementid);

        var pixelsinmm = 3.779527559055;
        var pdfx = Y.one('#pdf').getX();
        var pdfy = Y.one('#pdf').getY();
        var posx = pdfx + $('#editelementform #id_posx').val() * pixelsinmm;
        var posy = pdfy + $('#editelementform #id_posy').val() * pixelsinmm;
        var nodewidth = parseFloat(element.getComputedStyle('width'));
        var maxwidth = element.width * this.pixelsinmm;

        if (maxwidth && (nodewidth > maxwidth)) {
            nodewidth = maxwidth;
        }

        switch (refpoint) {
            case '1': // Top-center.
                posx -= nodewidth / 2;
                break;
            case '2': // Top-right.
                posx = posx - nodewidth + 2;
                break;
        }

        element.setX(posx);
        element.setY(posy);
    };

    RearrangeArea.prototype._getElementHTML = function(elementid) {
        // Get the variables we need.
        var templateid = this._node.attr('data-templateid');

        // Call the web service to get the updated element.
        var promises = ajax.call([{
            methodname: 'mod_customcert_get_element_html',
            args: {
                templateid : templateid,
                elementid : elementid
            }
        }]);

        // Return the promise.
        return promises[0];
    };

    RearrangeArea.prototype._saveElement = function(elementid) {
        // Get the variables we need.
        var templateid = this._node.attr('data-templateid');
        var inputs = $('#editelementform').serializeArray();

        // Call the web service to save the element.
        var promises = ajax.call([{
            methodname: 'mod_customcert_save_element',
            args: {
                templateid : templateid,
                elementid : elementid,
                values : inputs
            }
        }]);

        // Return the promise.
        return promises[0];
    };

    return {
        init : function(selector) {
            new RearrangeArea(selector);
        }
    };
});
