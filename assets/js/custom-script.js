jQuery(document).ready(function($){
	// ---------------------------
	// Create event ajax call
	// ----------------------------
	$("#create-event").on('click', function(){
	    $.confirm({
	    	columnClass: 'col-md-6 col-md-offset-3',
	        title: '<i class="nav-icon fas fa-info-circle"></i> Create Event',
	        content: '<form id="event-form">' +
	            '<div class="form-group">' +
	                '<label for="event-summary">Event Summary:</label>' +
	                '<input type="text" class="form-control" id="event-summary" name="event-summary" required>' +
	            '</div>' +
	            
	            '<div class="form-group">' +
	                '<label for="event-location">Event Location:</label>' +
	                '<input type="text" class="form-control" id="event-location" name="event-location">' +
	            '</div>' +
	            
	            '<div class="form-group">' +
	                '<label for="event-description">Event Description:</label>' +
	                '<input type="text" class="form-control" id="event-description" name="event-description">' +
	            '</div>' +
	            
	            '<div class="form-group">' +
	                '<label for="event-start">Event Start:</label>' +
	                '<input type="datetime-local" class="form-control" id="event-start" name="event-start" required>' +
	            '</div>' +
	            
	            '<div class="form-group">' +
	                '<label for="event-end">Event End:</label>' +
	                '<input type="datetime-local" class="form-control" id="event-end" name="event-end" required>' +
	            '</div>' +
	            
	            '</form>',
	        buttons: {
	            confirm: {
	                text: 'Create',
	                btnClass: 'btn btn-primary',
	                action: function () {

	                    var formData = {
	                        'summary': $('#event-summary').val(),
	                        'location': $('#event-location').val(),
	                        'description': $('#event-description').val(),
	                     	'start': {
	                                'dateTime': $('#event-start').val() + ':00',
	                                'timeZone': Intl.DateTimeFormat().resolvedOptions().timeZone,
	                            },
                            'end': {
	                                'dateTime': $('#event-end').val() + ':00',
	                                'timeZone': Intl.DateTimeFormat().resolvedOptions().timeZone,
	                            }
	                    };

	                    $("#google-calendar-events").addClass('loading');

	                    $.ajax({
	                        url: my_ajax_object.ajaxurl,
	                        type: "POST",
	                        data: {
	                         action: 'create_event',
	                         formData: formData
	                        },			                
	                        success: function(result){
	                        	if( result.success == true ){
	                        		$.ajax({
	                        		    url: my_ajax_object.ajaxurl,
	                        		    type: "GET",
	                        		    data: {
	                        		     action: 'list_event',
	                        		    },			                
	                        		    success: function(result){
	                        		      $("#google-calendar-events-container").html(result.data);
	                        		      $.confirm({
	                        		          title: '<i class="nav-icon fas fa-info-circle"></i>Success',
	                        		          content: "Event sucessfully created",
	                        		        buttons: {
	                        		          text: 'Ok',
	                        		          btnClass: 'btn-blue',
	                        		          confirm: {
	                        		            text: 'Ok',
	                        		            btnClass: 'btn-blue',
	                        		            action: function () { 	                        		            	
	                        		            }
	                        		          }
	                        		        }
	                        		      });
	                        		    },
	                        		    error: function(){
	                        		       $.alert('Something went wrong. Please contact support.');
	                        		    },
	                        		    complete: function(){
	                        		    	$("#google-calendar-events").removeClass('loading');
	                        		    }
	                        		});
	                        	}else{	  
	                        	  $("#google-calendar-events").removeClass('loading');                      	
				                  $.confirm({
				                      title: '<i class="nav-icon fas fa-info-circle"></i>Information',
				                      content: result.data,
				                      buttons: {
					                      text: 'Ok',
					                      btnClass: 'btn-blue',
					                      confirm: {
					                        text: 'Ok',
					                        btnClass: 'btn-blue',
					                        action: function () { 
					                        }
					                      }
				                    }
				                  });				                  
	                        	}
	                        },
	                        error: function(){
	                           $("#google-calendar-events").removeClass('loading');
	                           $.alert('Something went wrong. Please contact support.');
	                        }
	                    });

	                }
	            },
	            cancel: {
	                text: 'Cancel',
	                btnClass: 'btn btn-secondary',
	                action : function () {
	                    // Handle cancellation
	                }
	            }
	        }
	    });
	});

	// ------------------------------
	// Delete event ajax call
	// ------------------------------
	$(".delete-event").on('click', function(){
		var clickedItem = $(this);
		$.confirm({
		    title: '<i class="nav-icon fas fa-info-circle"></i> Delete',
		    content: 'Are you sure you want to delete event ?',
		    buttons: {
		        confirm: {
		          text: 'Delete',
		          btnClass: 'btn-blue',
		          action: function () {
		            $("#google-calendar-events").addClass('loading');
		            var eventID = clickedItem.data('eventid');
		            $.ajax({
		                url: my_ajax_object.ajaxurl,
		                type: "POST",
		                data: {
		                 action: 'delete_event',
		                 event_id: eventID
		                },			                
		                success: function(result){

			               if( result.success == true ){
			                	$("#elem-"+eventID).remove();
			               }	                
		                  $.confirm({
		                      title: '<i class="nav-icon fas fa-info-circle"></i>Information',
		                      content: result.data,
		                      buttons: {
			                      text: 'Ok',
			                      btnClass: 'btn-blue',
			                      confirm: {
			                        text: 'Ok',
			                        btnClass: 'btn-blue',
			                        action: function () { 
			                        }
			                      }
		                    }
		                  });
		                },
		                error: function(error){
		                   $.alert('something went wrong. Please contact support.');
		                },
		                complete: function(){
		                	$("#google-calendar-events").removeClass('loading');
		                }
		            });
		          }
		        },
		        cancel: {
		          text: 'Cancel',
		          btnClass: 'btn-red',
		          action : function () {

		          }
		        }
		    }
		});
	});

	// ----------------------------------
	// Disconnect from google calendar 
	// -----------------------------------
	$("#intujiJTGC-disconnect-ac").on('click', function(){
		$.confirm({
		    title: '<i class="nav-icon fas fa-info-circle"></i> Information',
		    content: 'Are you sure you want discontinue google account ?',
		    buttons: {
		        confirm: {
		          text: 'Ok',
		          btnClass: 'btn-blue',
		          action: function () {
		            $("#google-calendar-events").addClass('loading');		            
		            $.ajax({
		                url: my_ajax_object.ajaxurl,
		                type: "GET",
		                data: {
		                 action: 'disconnect_google_calendar',		                 
		                },			                
		                success: function(result){

			               if( result.success == true ){
			               		location.reload();
			               }else{
	       	                  $.confirm({
	       	                      title: '<i class="nav-icon fas fa-info-circle"></i>Information',
	       	                      content: result.data,
	       	                      buttons: {
	       		                      text: 'Ok',
	       		                      btnClass: 'btn-blue',
	       		                      confirm: {
	       		                        text: 'Ok',
	       		                        btnClass: 'btn-blue',
	       		                        action: function () { 
	       		                        }
	       		                      }
	       	                    }
	       	                  });
			               }	                
		                },
		                error: function(error){
		                   $.alert('something went wrong. Please contact support.');
		                },
		                complete: function(){
		                	$("#google-calendar-events").removeClass('loading');
		                }
		            });
		          }
		        },
		        cancel: {
		          text: 'Cancel',
		          btnClass: 'btn-red',
		          action : function () {

		          }
		        }
		    }
		});
	});

});