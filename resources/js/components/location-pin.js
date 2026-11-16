// "Pin my location" on the member dashboard: asks the phone once, then submits the form.
// One point, replaced each time the member re-pins. Nothing runs until they tap.
export default () => ({
    status: 'idle',
    message: '',

    pin() {
        if (!('geolocation' in navigator)) {
            this.status = 'error';
            this.message = 'Your browser cannot share its location. Try on your phone.';
            return;
        }
        this.status = 'busy';
        this.message = 'Finding you…';
        navigator.geolocation.getCurrentPosition(
            (pos) => {
                this.$refs.lat.value = pos.coords.latitude.toFixed(6);
                this.$refs.lng.value = pos.coords.longitude.toFixed(6);
                this.message = 'Saving…';
                this.$refs.form.submit();
            },
            (e) => {
                this.status = 'error';
                this.message = e.code === 1
                    ? 'Location access is blocked. Allow it for this site in your browser settings, then try again.'
                    : 'We could not get your location. Move somewhere with a clearer sky and try again.';
            },
            { enableHighAccuracy: true, timeout: 20000, maximumAge: 60000 },
        );
    },
});
