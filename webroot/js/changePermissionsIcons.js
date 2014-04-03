/*
 * Change Permissions Icons
 *
 * A jQuery extension to change controllers's permissions icons.
 *
 * @author Gabriel Pedote :: gabriel.pedote@fatec.sp.gov.br
 */

if (typeof jQuery != 'undefined') {
	$('select') .on('change', function () {
	    select = $(this);
	    tableCell = $(this) .parent();
	    if (tableCell.attr('isparent') === 'true') {
	        dataLevel = tableCell.attr('data-level');
	        dataParent = tableCell.attr('data-parent');

	        $('td').each(function () {
	            if ($(this).attr('data-level') == dataLevel 
	                && $(this).attr('data-parent') == dataParent) {
	                $(this).children('i') .removeClass();
	                switch (select.val()) {
	                    case 'deny':
	                        $(this).children('select') .val('deny');
	                        $(this).children('i') .addClass('fa fa-times');
	                    break;
	                    case 'allow':
	                        $(this).children('select') .val('allow');
	                        $(this).children('i') .addClass('fa fa-check');
	                    break;
	                    case 'inherit':
	                        $(this).children('select') .val('inherit');
	                        $(this).children('i') .addClass('fa fa-arrow-up');
	                    break;
	                    case '':
	                        $(this).children('select') .val('');
	                        $(this).children('i') .addClass('fa fa-minus');
	                    break;
	                }
	            }
	        });
	    } else if (tableCell.attr('isparent') === 'false') {
	        tableCell.children('i') .removeClass();
	        switch (select.val()) {
	            case 'deny':
	                tableCell.children('i') .addClass('fa fa-times');
	            break;
	            case 'allow':
	                tableCell.children('i') .addClass('fa fa-check');
	            break;
	            case 'inherit':
	                tableCell.children('i') .addClass('fa fa-arrow-up');
	            break;
	            case '':
	                tableCell.children('i') .addClass('fa fa-minus');
	            break;
	        }
	    }
	});
}