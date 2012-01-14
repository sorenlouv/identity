var recursiveSearch;
var options,
	possibilities, 
	dob, 
	firstName, 
	lastName, 
	verified;

$(document).ready(function() {
	$('#findValidNumbers').click(function(){
	
		// reset
		options = [];
		possibilities = [];
		verified = 0;
		$(".verified").html("");
				
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
			// no: this is the last layer. we add the result to the array
			var proposedCprNumber = number + options[depth][i]; 			
			if(testCPR(proposedCprNumber)){
				verified = (verified + 1);
				
				// cpr number could be valid. Post it
				if(verified < 80){
					$(".verified").append(proposedCprNumber + "<br/>");
					possibilities.push ( proposedCprNumber );
					$.post('post.php', {'cpr': proposedCprNumber, 'dob':dob, 'firstName': firstName, 'lastName': lastName}, function(response, proposedCprNumber){	
						console.log(response);
						if(response.indexOf("success") != -1){
							alert(response);
							return false;
						}
					});
				}
			}
		}
	}
}

function testCPR(lastfour){
	var cprnr = dob + lastfour;
	var sum = 0;	
	var factors = [ 4, 3, 2, 7, 6, 5, 4, 3, 2, 1 ];
	
	for (i = 0; i < 10; i++)	{
		sum += cprnr.substring(i, i+1) * factors[i];
	}
	
	if ((sum % 11) != 0)	{
		return false;
	}	else {
		return true;
	}
}
