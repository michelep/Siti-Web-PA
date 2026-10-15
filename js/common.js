// common JavaScript functions

function getStats() {
	$.ajax({
		url: "/ajax/get_stats",
		method: "POST",
		dataType: "json",
		data: {"action": "get_stats"},
		success: function (data) {
		    $("#display_enti_totali").html(data.enti_totali);
		    $("#display_siti_web").html(data.enti_siti_web);
		    $("#display_domini_checked").html(data.enti_mx_totali);
		    $("#display_mx_google").html(data.enti_mx_google);
		},
		error: function(xhr, textStatus){
		    $("#display_error").html(textStatus);
		},
		timeout: 30000
	}).always(function () {
	    setTimeout(getStats, 30000)
	})
}

$('#table').bootstrapTable({
  onClickRow: function (row, $element) {
    const rowId = row.codice_ipa;
    window.location.href = `/details/${rowId}`;
  }
});

$(document).ready(function() {
    getStats();
});

