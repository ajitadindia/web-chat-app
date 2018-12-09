// MySQL API
var apis = 'api.php';

// set image directori
var imageDir = 'image';

// Replace with: your firebase account
var config = {
	apiKey: "",
	authDomain: "",
	databaseURL: "",
	projectId: "",
	storageBucket: "",
	messagingSenderId: ""
};

firebase.initializeApp(config);

// create firebase child
var dbRef = firebase.database().ref(),
messageRef = dbRef.child('message'),
userRef = dbRef.child('user');
