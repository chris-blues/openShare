var users = [], dataDiv, username = [], characters, charactersUsed, nameAlreadyUsed, pwMismatch, pwTooShort;

function init()
  {
   users = document.getElementsByClassName('usernames');
   console.log("users is " + typeof users + " with length: " + users.length);
   //for (i=0;i<users.length;i++) { console.log("user " + i + "/" + users.length + " is called " + users[i].getAttribute("data-name") + "."); }

   dataDiv = document.getElementById("stuff");
   console.log("dataDiv is " + typeof dataDiv);

   if (typeof dataDiv !== null)
     {
      console.log("dataDiv is not null!");
      for ( i = 0; i < users.length; i++ )
        {
         username[i] = users[i].getAttribute("data-name");
	 console.log("user " + i + " = " + username[i]);
        }
     }

   characters = dataDiv.getAttribute("data-characters");
   charactersUsed = dataDiv.getAttribute("data-charactersUsed");
   nameAlreadyUsed = dataDiv.getAttribute("data-nameAlreadyUsed");
   pwMismatch = dataDiv.getAttribute("data-pwMismatch");
   pwTooShort = dataDiv.getAttribute("data-pwTooShort");

   document.getElementById("checkAndSubmit").addEventListener("click", function() { checkInput(); });
  }

function checkInput ()
  {
   var error = false;
   name = document.getElementById("name").value;
   document.getElementById("error").innerHTML = "";
   for (var i = 0; i < username.length; i++)
     {
      illChar = name.match(/[^A-Za-z0-9_.-]/g);
      if (illChar != null)
        {
         error = true;
         namestring = name.replace(/[^A-Za-z0-9_.-]/g, function markIllChar(x) { return '<b><i><span class="illChar">' + x + '</span></i></b>'; });
         document.getElementById("error").style.display = "block";
         document.getElementById("error").innerHTML += characters + "<br>" + charactersUsed + '<span class="nameError">' + namestring + "</span><br>";
        }
      if (username[i] == name)
        {
         error = true;
         document.getElementById("error").style.display = "block";
         document.getElementById("error").innerHTML += nameAlreadyUsed + "<br>";
	}
     }
   passwd = document.getElementById('passwd');
   confirmPasswd = document.getElementById('confirmPasswd');
   if (passwd.value == confirmPasswd.value && passwd.value != "" && document.getElementById('name').value != "")
     {  }
   else
     {
      error = true;
      passwd.style.backgroundColor = "red";
      passwd.value = "";
      confirmPasswd.style.backgroundColor = "red";
      confirmPasswd.value = "";
      document.getElementById("error").style.display = "block";
      document.getElementById("error").innerHTML += pwMismatch + "<br>";
     }
   if (passwd.value.length < 5)
     {
      error = true;
      passwd.style.backgroundColor = "red";
      passwd.value = "";
      confirmPasswd.style.backgroundColor = "red";
      confirmPasswd.value = "";
      document.getElementById("error").style.display = "block";
      document.getElementById("error").innerHTML += pwTooShort + "<br>";
     }
   if (error != true) document.getElementById("setPasswd").submit();
  }

addEventListener("load", function() { init(); });