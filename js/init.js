$('.rateit').bind('reset rated', function(e) {
	var ri = $(this);

	// If the user pressed reset, it will get value: 0
	var value       = ri.rateit('value');
	var record_id   = ri.data('bolt-record-id');
	var contenttype = ri.data('bolt-contenttype');

	$.ajax({
		url : asyncpath,
		data : {
			record_id : record_id,
			contenttype : contenttype,
			value : value
		},
		type : 'POST',
		success : function(data) {
			var retval = data.retval;
			var msg = data.msg;
			$('#rateit_response-' + record_id).html(msg);
			$('#rateit_response-' + record_id).show();
			
			// Update the default value
			$('.rateit').data('bolt-rateit-value', data.value);
		},
		error : function(jxhr, msg, err) {
			if (jxhr.status == 418) {
				// Vote stuffing detected, reset to pre-press
				$('.rateit').rateit('value', $('.rateit').data('bolt-rateit-value'));
		    }

			$('#rateit_response-' + record_id).html(jxhr.responseText);
			$('#rateit_response-' + record_id).show();
		},
		dataType : 'json'
	});
});

$(document).ready(function(){
    $('.rateit').each(function(){
        $(this).rateit('value', $(this).data('bolt-rateit-value'));
    });
});
