var Main = {
	quote : function(id){ // quoting
		document.form.comment.focus();
		if(document.selection){
			range = document.selection.createRange();
			range.text = ">>" + id + "\n";
		} else if(document.form.comment.selectionStart || document.form.comment.selectionStart == "0"){
			var before = document.form.comment.selectionStart;
			var after = document.form.comment.selectionEnd;
			document.form.comment.value = document.form.comment.value.substring(0,before) +
			">>" + id + "\n" +
			document.form.comment.value.substring(after,document.form.comment.value.length);
		} else {
			document.form.comment.value += ">>" + id + "\n";
		}
	}
}

$(document).ready(function(){
	$('input:first').each(function(){
		$(this).focus();
	});
});