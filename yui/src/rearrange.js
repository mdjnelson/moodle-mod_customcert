M.mod_customcert = {};


M.mod_customcert.rearrange = {

    /**
     * The course module id.
     */
    cmid : 0,

    /**
     * The custom certificate elements to display.
     */
    elements : Array(),

    /**
     * Store the X coordinates of the top left of the pdf div.
     */
    pdfx : 0,

    /**
     * Store the Y coordinates of the top left of the pdf div.
     */
    pdfy : 0,

    /**
     * Store the width of the pdf div.
     */
    pdfwidth : 0,

    /**
     * Store the height of the pdf div.
     */
    pdfheight : 0,

    /**
     * Store the location of the element before we move.
     */
    elementxy : 0,

    /**
     * The number of pixels in a mm.
     */
    pixelsinmm : 3.779527559055, //'3.779528',

    /**
     * Initialise.
     *
     * @param Y
     * @param elements
     */
    init : function(Y, cmid, elements) {
        // Set the course module id.
        this.cmid = cmid;
        // Set the elements.
        this.elements = elements;

        // Set the PDF dimensions.
        this.pdfx = Y.one('#pdf').getX();
        this.pdfy = Y.one('#pdf').getY();
        this.pdfwidth = parseFloat(Y.one('#pdf').getComputedStyle('width'), 10);
        this.pdfheight = parseFloat(Y.one('#pdf').getComputedStyle('height'), 10);

        this.set_data(Y);
        this.set_positions(Y);
        this.create_events(Y);
        // this.set_positions(Y); // move here
    },

    /**
     * Sets the additional data for the elements.
     *
     * @param Y
     */
    set_data : function(Y) {
        // Go through the elements and set their reference points.
        for (var key in this.elements) {
            var element = this.elements[key];
            Y.one('#element-' + element['id']).setData('refpoint', element['refpoint']);
            Y.one('#element-' + element['id']).setData('width', element['width']);
        }
    },

    /**
     * Sets the current position of the elements.
     *
     * @param Y
     */
    set_positions : function(Y) {
        // Go through the elements and set their positions.
        for (var key in this.elements) {
            var element = this.elements[key];
            var posx = this.pdfx + element['posx'] * this.pixelsinmm;
            var posy = this.pdfy + element['posy'] * this.pixelsinmm;
            var nodewidth = parseFloat(Y.one('#element-' + element['id']).getComputedStyle('width'), 10);
            var maxwidth = element['width'] * this.pixelsinmm;

            if (maxwidth && (nodewidth > maxwidth)) {
                nodewidth = maxwidth;
            }
            Y.one('#element-' + element['id']).setStyle('width', nodewidth + 'px');

            switch (element['refpoint']) {
                case '1':   // Top-center
                    posx -= nodewidth / 2;
                    break;
                case '2':   // Top-right
                    posx = posx - nodewidth + 2;
                    break;
            }

            Y.one('#element-' + element['id']).setX(posx);
            Y.one('#element-' + element['id']).setY(posy);

            this.resize_if_required(Y.one('#element-' + element['id']));
        }
    },

    /**
     * Creates the JS events for changing element positions.
     *
     * @param Y
     */
    create_events : function(Y) {
        // Trigger a save event when save button is pushed.
        Y.one('.savepositionsbtn input[type=submit]').on('click', function(e) {
            this.save_positions(e);
        }, this);

        // Trigger a save event when apply button is pushed.
        Y.one('.applypositionsbtn input[type=submit]').on('click', function(e) {
            this.save_positions(e);
            e.preventDefault();
        }, this);

        // Define the container and the elements that are draggable.
        var del = new Y.DD.Delegate({
            container: '#pdf',
            nodes: '.element'
        });

        // When we start dragging keep track of it's position as we may set it back.
        del.on('drag:start', function() {
            var node = del.get('currentNode');
            this.elementxy = node.getXY();
            this.elementwidth = node.getComputedStyle('width');
        }, this);

        // When we finish the dragging action check that the node is in bounds,
        // if not, set it back to where it was.
        del.on('drag:end', function() {
            var node = del.get('currentNode');
            this.resize_if_required(node);
            if (this.is_out_of_bounds(node)) {
                node.setXY(this.elementxy);
                node.setStyle('width', this.elementwidth);
            }
        }, this);
    },

    /**
     * Resizes the element if required.
     *
     * @param node
     * @returns {boolean}
     */
    resize_if_required : function(node) {
        var refpoint = node.getData('refpoint');
        var maxwidth = node.getData('width') * this.pixelsinmm;
        var maxallowedwidth = 0;
        var oldwidth = 0;

        // Get the width and height of the node.
        var nodewidth = parseFloat(node.getComputedStyle('width'), 10);
        var nodeheight = parseFloat(node.getComputedStyle('height'), 10);

        // Store the positions of each edge of the node.
        var left = node.getX();
        var right = left + nodewidth;
        var top = node.getY();
        var bottom = top + nodeheight;

        node.setStyle('width', 'initial');

        oldwidth = nodewidth;
        nodewidth = parseFloat(node.getComputedStyle('width'), 10);

        switch (refpoint) {
            case '1':   // Top-center
                left = left + (oldwidth - nodewidth) / 2;
                if (maxwidth && nodewidth > maxwidth) {
                    left = left + (nodewidth - maxwidth) / 2;
                    nodewidth = maxwidth;
                    node.setStyle('width', nodewidth + 'px');
                }
                maxallowedwidth = 2 * Math.min(left + nodewidth / 2 - this.pdfx, this.pdfx + this.pdfwidth - (left + nodewidth / 2));
                if (maxallowedwidth > 0 && nodewidth > maxallowedwidth) {
                    left = left + (nodewidth - maxallowedwidth) / 2;
                    nodewidth = maxallowedwidth;
                    node.setStyle('width', nodewidth + 'px');
                }
                break;
            case '2':   // Top-right
                left = left + oldwidth - nodewidth;
                if (maxwidth && nodewidth > maxwidth) {
                    left = left + nodewidth - maxwidth;
                    nodewidth = maxwidth;
                    node.setStyle('width', nodewidth + 'px');
                }
                maxallowedwidth = left + nodewidth - this.pdfx;
                if (maxallowedwidth > 0 && nodewidth > maxallowedwidth) {
                    left = this.pdfx;
                    nodewidth = maxallowedwidth;
                    node.setStyle('width', nodewidth + 'px');
                }
                break;
            case '0':   // Top-left
            default:
                if (maxwidth && nodewidth > maxwidth) {
                    nodewidth = maxwidth;
                    node.setStyle('width', nodewidth + 'px');
                }
                maxallowedwidth = this.pdfx + this.pdfwidth - left;
                if (maxallowedwidth > 0 && nodewidth > maxallowedwidth) {
                    nodewidth = maxallowedwidth;
                    node.setStyle('width', nodewidth + 'px');
                }
        }

        node.setX(left);
    },

    /**
     * Returns true if any part of the element is placed outside of the PDF div, false otherwise.
     *
     * @param node
     * @returns {boolean}
     */
    is_out_of_bounds : function(node) {
        // Get the width and height of the node.
        var nodewidth = parseFloat(node.getComputedStyle('width'), 10);
        var nodeheight = parseFloat(node.getComputedStyle('height'), 10);

        // Store the positions of each edge of the node.
        var left = node.getX();
        var right = left + nodewidth;
        var top = node.getY();
        var bottom = top + nodeheight;

        // Check if it is out of bounds horizontally.
        if ((left < this.pdfx) || (right > (this.pdfx + this.pdfwidth)))  {
            return true;
        }

        // Check if it is out of bounds vertically.
        if ((top < this.pdfy) || (bottom > (this.pdfy + this.pdfheight)))  {
            return true;
        }

        return false;
    },

    /**
     * Perform an AJAX call and save the positions of the elements.
     *
     * @param e
     */
    save_positions : function(e) {
        // The parameters to send the AJAX call.
        var params = {
            cmid: this.cmid,
            values: []
        };

        // Go through the elements and save their positions.
        for (var key in this.elements) {
            var element = this.elements[key];
            var node = Y.one('#element-' + element['id']);

            // Get the current X and Y positions for this element.
            var posx = node.getX() - this.pdfx;
            var posy = node.getY() - this.pdfy;

            var nodewidth = parseFloat(node.getComputedStyle('width'), 10);

            switch (element['refpoint']) {
                case '1':   // Top-center
                    posx += nodewidth / 2;
                    break;
                case '2':   // Top-right
                    posx += nodewidth;
                    break;
            }

            // Set the parameters to pass to the AJAX request.
            params.values.push({
                id: element['id'],
                posx: Math.round(parseFloat(posx / this.pixelsinmm, 10)),
                posy: Math.round(parseFloat(posy / this.pixelsinmm, 10))
            });
        }

        params.values = JSON.stringify(params.values);

        // Save these positions.
        Y.io(M.cfg.wwwroot + '/mod/customcert/rest.php', {
            method: 'POST',
            data: params,
            on: {
                failure: function(tid, response) {
                    this.ajax_failure(response);
                    e.preventDefault();
                }
            },
            context: this
        })

    },

    /**
     * Handles any failures during an AJAX call.
     *
     * @param response
     * @returns {M.core.exception}
     */
    ajax_failure : function(response) {
        var e = {
            name: response.status + ' ' + response.statusText,
            message: response.responseText
        };
        return new M.core.exception(e);
    }
}