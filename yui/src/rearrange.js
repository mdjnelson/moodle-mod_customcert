M.mod_customcert = {};


M.mod_customcert.rearrange = {

    /**
     * The course module id.
     */
    cmid : 0,

    /**
     * The customcert page we are displaying.
     */
    page : Array(),

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
     * Store the left boundary of the pdf div.
     */
    pdfleftboundary : 0,

    /**
     * Store the right boundary of the pdf div.
     */
    pdfrightboundary : 0,


    /**
     * The number of pixels in a mm.
     */
    pixelsinmm : 3.779527559055, //'3.779528',

    /**
     * Initialise.
     *
     * @param Y
     * @param cmid
     * @param page
     * @param elements
     */
    init : function(Y, cmid, page, elements) {
        // Set the course module id.
        this.cmid = cmid;
        // Set the page.
        this.page = page;
        // Set the elements.
        this.elements = elements;

        // Set the PDF dimensions.
        this.pdfx = Y.one('#pdf').getX();
        this.pdfy = Y.one('#pdf').getY();
        this.pdfwidth = parseFloat(Y.one('#pdf').getComputedStyle('width'), 10);
        this.pdfheight = parseFloat(Y.one('#pdf').getComputedStyle('height'), 10);

        // Set the boundaries.
        this.pdfleftboundary = this.pdfx;
        if (this.page['leftmargin']) {
            this.pdfleftboundary += parseInt(this.page['leftmargin'] * this.pixelsinmm);
        }

        this.pdfrightboundary = this.pdfx + this.pdfwidth;
        if (this.page['rightmargin']) {
            this.pdfrightboundary -= parseInt(this.page['rightmargin'] * this.pixelsinmm);
        }

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
            if (this.is_out_of_bounds(node)) {
                node.setXY(this.elementxy);
                node.setStyle('width', this.elementwidth);
            }
        }, this);
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
        if ((left < this.pdfleftboundary) || (right > this.pdfrightboundary))  {
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
                },
                success: function() {
                    var formNode = e.currentTarget.ancestor('form', true);
                    window.location = formNode.getAttribute('action') + '?' + Y.QueryString.stringify({cmid:formNode.one('[name=cmid]').get('value')});
                }
            },
            context: this
        });

        e.preventDefault();

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