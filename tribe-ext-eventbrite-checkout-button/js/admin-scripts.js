jQuery(document).ready(function($) {
    var $eventbriteFieldsWrapper = $('#eventbrite-fields-wrapper');

    // Function to update the "Ticket Option X" labels and input names after sorting/adding/removing
    function updateFieldIndexesAndLabels() {
        $eventbriteFieldsWrapper.find('.eventbrite-field-row').each(function(index) {
            var $row = $(this);
            var newIndex = index;

            // Update the label
            $row.find('.ticket-option-label strong').text(eventbrite_ticket_button_vars.entry_label_prefix + ' ' + (newIndex + 1));

            // Update name and id attributes for textarea
            $row.find('textarea[name*="[html]"]').attr({
                'name': '_tribe_ext_eventbrite_entries[' + newIndex + '][html]',
                'id': '_tribe_ext_eventbrite_entries[' + newIndex + '][html]'
            });

            // Update name and id attributes for url input
            $row.find('input[name*="[url]"]').attr({
                'name': '_tribe_ext_eventbrite_entries[' + newIndex + '][url]',
                'id': '_tribe_ext_eventbrite_entries[' + newIndex + '][url]'
            });

            // Ensure remove buttons are visible if there's more than one row, or if the single row has content
            if ($eventbriteFieldsWrapper.find('.eventbrite-field-row').length > 1 || (!($row.find('textarea[name*="[html]"]').val() === '' && $row.find('input[name*="[url]"]').val() === ''))) {
                $row.find('.remove-eventbrite-field').show();
            } else {
                $row.find('.remove-eventbrite-field').hide();
            }
        });
    }

    // Initialize Sortable
    $eventbriteFieldsWrapper.sortable({
        items: '.eventbrite-field-row', // What items are sortable
        handle: '.sort-handle', // The handle for dragging (updated class)
        cursor: 'grabbing',
        axis: 'y', // Only allow vertical sorting
        placeholder: 'ui-sortable-placeholder', // Class for the placeholder when dragging
        forcePlaceholderSize: true, // Placeholder takes the size of the dragged item
        opacity: 0.8, // Opacity of the helper element
        update: function(event, ui) {
            // This event fires when the user stops dragging and the order has changed.
            updateFieldIndexesAndLabels(); // Re-index labels and names immediately after sorting
        }
    });

    // Handler for adding new Eventbrite field rows
    $('#add-new-eventbrite-field').on('click', function() {
        var $lastFieldRow = $eventbriteFieldsWrapper.find('.eventbrite-field-row:last');
        var newIndex = 0;

        if ($lastFieldRow.length > 0) {
            var lastNameAttribute = $lastFieldRow.find('textarea[name*="_tribe_ext_eventbrite_entries"]').attr('name');
            if (lastNameAttribute) {
                var match = lastNameAttribute.match(/\[(\d+)\]/);
                if (match && match[1]) {
                    newIndex = parseInt(match[1]) + 1;
                }
            }
        }

        // Updated HTML structure for a new field row
        var newFieldHtml = `
            <div class="eventbrite-field-row">
                <div class="ticket-option-header">
                    <span class="dashicons dashicons-menu sort-handle"></span>
                    <p class="ticket-option-label"><strong>${eventbrite_ticket_button_vars.entry_label_prefix} ${newIndex + 1}</strong></p>
                </div>
                <div class="tribe-ext-custom-html-field">
                    <label for="_tribe_ext_eventbrite_entries[${newIndex}][html]">
                        ${eventbrite_ticket_button_vars.embed_code_label}:
                    </label>
                    <textarea
                        id="_tribe_ext_eventbrite_entries[${newIndex}][html]"
                        name="_tribe_ext_eventbrite_entries[${newIndex}][html]"
                        rows="5"
                        class="large-text code"
                    ></textarea>
                    <p class="description">
                        ${eventbrite_ticket_button_vars.embed_code_desc}
                    </p>
                    <p class="description">
                        ${eventbrite_ticket_button_vars.embed_code_info}
                    </p>
                </div>
                <div class="tribe-ext-custom-link-field">
                    <label for="_tribe_ext_eventbrite_entries[${newIndex}][url]">
                        ${eventbrite_ticket_button_vars.url_label}:
                    </label>
                    <input
                        type="url"
                        id="_tribe_ext_eventbrite_entries[${newIndex}][url]"
                        name="_tribe_ext_eventbrite_entries[${newIndex}][url]"
                        value=""
                        class="large-text"
                    />
                    <p class="description">
                        ${eventbrite_ticket_button_vars.url_desc}
                    </p>
                </div>
                <button type="button" class="button remove-eventbrite-field">
                    ${eventbrite_ticket_button_vars.remove_button_text}
                </button>
            </div>
        `;

        $eventbriteFieldsWrapper.append(newFieldHtml);
        updateFieldIndexesAndLabels(); // Re-index all labels and names after adding
    });

    // Handler for removing Eventbrite field rows (using event delegation)
    $eventbriteFieldsWrapper.on('click', '.remove-eventbrite-field', function() {
        if (confirm(eventbrite_ticket_button_vars.confirm_remove_text)) {
            $(this).closest('.eventbrite-field-row').remove();
            updateFieldIndexesAndLabels(); // Re-index all labels and names after removing
        }
    });

    // Initial call to set up remove button visibility correctly
    updateFieldIndexesAndLabels();

    // Re-evaluate visibility when fields change (simple debounce for performance)
    $eventbriteFieldsWrapper.on('input', 'textarea, input', function() {
        clearTimeout($(this).data('timeout'));
        $(this).data('timeout', setTimeout(updateFieldIndexesAndLabels, 500));
    });
});