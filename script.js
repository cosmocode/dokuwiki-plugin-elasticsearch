jQuery(function () {

    /**
     * Autocomplete for elasticsearch
     *
     * This can be used as jQueryUI.autocomplete source
     *
     * @param {object} request
     * @param {function} response
     */
    const elasticAutoComplete = function (request, response) {
        const data = {
            q: request.term,
            call: 'elasticsearch_autocomplete',
            min: 0 // FIXME get from form
        };
        jQuery.post(DOKU_BASE + 'lib/exe/ajax.php', data, response, 'json');
    };

    // Attach autocomplete to our search form
    jQuery('.elastic-form input[name="q"]').autocomplete({
        source: elasticAutoComplete
    });

    // FIXME attach to standard quicksearch form
});
