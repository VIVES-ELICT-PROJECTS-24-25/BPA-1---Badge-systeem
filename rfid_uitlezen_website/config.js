// Client-side Firebase configuratie
// Dit vindt u in de Firebase Console > Projectinstellingen > Uw apps
const firebaseConfig = {
    apiKey: "AIzaSyA_HIER_KOMT_UW_FIREBASE_API_KEY",
    authDomain: "maaklab-project.firebaseapp.com",
    databaseURL: "https://maaklab-project-default-rtdb.europe-west1.firebasedatabase.app",
    projectId: "maaklab-project",
    storageBucket: "maaklab-project.appspot.com",
    messagingSenderId: "UW_MESSAGING_SENDER_ID",
    appId: "UW_APP_ID"
};

// Initialize Firebase
firebase.initializeApp(firebaseConfig);
const database = firebase.database();
