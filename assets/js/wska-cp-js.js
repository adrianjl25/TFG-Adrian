jQuery(document).ready(function ($) {
    $('#url-form').on('submit', function (e) {
        e.preventDefault();

        const url = $('#url-input').val();
        const resultsDiv = $('#results');
        const vennDiv = $('#venn');

        resultsDiv.html('<p>Procesando...</p>');
        vennDiv.html('');

        $.ajax({
            url: wska_ajax_object.ajax_url,
            method: 'POST',
            data: {
                action: 'wska_procesar_url',
                nonce: wska_ajax_object.nonce,
                url: url,
            },
            success: function (response) {
                if (response.success) {
                    const { keywords, vennData } = response.data;

                    resultsDiv.html('<h3>Similitud de palabras clave:</h3><ul>');
                    for (const [keyword, count] of Object.entries(keywords)) {
                        resultsDiv.append(`<li><strong>${keyword}:</strong> ${count}</li>`);
                    }
                    resultsDiv.append('</ul>');

                    const chart = venn.VennDiagram();
                    d3.select('#venn').datum(vennData).call(chart);
                } else {
                    resultsDiv.html(`<p>${response.data.message}</p>`);
                }
            },
            error: function () {
                resultsDiv.html('<p>Error al procesar la solicitud.</p>');
            },
        });
    });
});
