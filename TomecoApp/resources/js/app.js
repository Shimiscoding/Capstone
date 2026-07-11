import './bootstrap';

if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('/sw.js', { scope: '/' }).then(function (registration) {
        registration.update();
        console.log(`SW registered successfully!`);
    }).catch(function (registrationError) {
        console.log(`SW registration failed`);
    });
}
