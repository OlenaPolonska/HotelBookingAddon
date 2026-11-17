jQuery(document).ready(function($) {
    var pendingRooms = [];
    var roomsToRemove = [];

    $('#regina-room-type').on('change', function() {

        var typeId = $(this).val();
        var checkIn = $('#mphb-mphb_check_in_date').val();
        var checkOut = $('#mphb-mphb_check_out_date').val();

        if (!typeId || !checkIn || !checkOut) {
            $('#regina-room, #regina-rate').prop('disabled', true).html('<option value="">— Select Type and Dates First —</option>');
            return;
        }

        $('#regina-room, #regina-rate').prop('disabled', true).html('<option>Loading...</option>');
        
        $.ajax({
            url: ajaxurl,
            data: {
                action: 'regina_get_room_data',
                room_type_id: typeId,
                check_in: checkIn,
                check_out: checkOut,
                booking_id: $('#post_ID').val(),
                _ajax_nonce: regina_hba.nonce
            },
            success: function(response) {
                if (response.success) {
                    var roomHtml = '<option value="">— Select Accommodation —</option>';
                    $.each(response.data.rooms, function(id, title) {
                        roomHtml += '<option value="' + id + '">' + title + '</option>';
                    });
                    $('#regina-room').html(roomHtml).prop('disabled', false);

                    var rateHtml = '<option value="">— Select Rate —</option>';
                    $.each(response.data.rates, function(id, title) {
                        rateHtml += '<option value="' + id + '">' + title + '</option>';
                    });
                    $('#regina-rate').html(rateHtml).prop('disabled', false);
                } else {
                    $('#regina-room, #regina-rate').html('<option value="">' + response.data.message + '</option>');
                }
            }
        });
    });

    $('#regina-add-room-btn').on('click', function() {
        var roomType = $('#regina-room-type option:selected').text();
        var roomTypeId = $('#regina-room-type').val();
        var room = $('#regina-room option:selected').text();
        var roomId = $('#regina-room').val();
        var rate = $('#regina-rate option:selected').text();
        var rateId = $('#regina-rate').val();
        var adults = $('input[name="regina_new_room[adults]"]').val();
        var children = $('input[name="regina_new_room[children]"]').val();

        if (!roomId || !rateId) {
            alert('Please select accommodation and rate.');
            return;
        }

        var roomData = {
            room_type_id: roomTypeId,
            room_id: roomId,
            rate_id: rateId,
            adults: adults,
            children: children
        };

        pendingRooms.push(roomData);
        
        var html = '<div class="regina-pending-room">';
        html += '<strong>' + roomType + ' (' + room + ')</strong> - ' + rate + ' (Adults: ' + adults + ', Children: ' + children + ')';
        html += '<input type="hidden" name="regina_pending_rooms[]" value=\'' + JSON.stringify(roomData).replace(/'/g, "&apos;") + '\' />';
        html += '</div>';
        
        $('#regina-pending-rooms').append(html);
    });

    $('.regina-remove-room').on('click', function() {
        var id = $(this).data('id');
        roomsToRemove.push(id);
        $('#regina-rooms-to-remove').val(roomsToRemove.join(','));
        $(this).closest('tr').addClass('regina-marked-for-removal').css('text-decoration', 'line-through').css('opacity', '0.5');
        $(this).prop('disabled', true).text('Marked for removal');
    });

    $('form#post').on('submit', function() {
        var totalActive = $('#regina-reserved-rooms-wrapper table tbody tr:not(.regina-marked-for-removal)').length + $('.regina-pending-room').length;
        if ( totalActive <= 0 ) {
            alert('Please add at least one accommodation before saving the booking.');
            $('#regina-room-type').focus();
            $('#publish').removeClass('disabled');
            return false;
        }
    });
});
