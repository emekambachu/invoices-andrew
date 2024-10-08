import axios from "axios"
import apiClientAuto from "@/utils/apiCLientAuto.js";
import handleErrors from "@/utils/handleErrors.js";

const RouteService = {

    // Authenticate each api request
    authenticateUser(url, next, logout){

        if(import.meta.env.VITE_APP_ENV === 'local') {
            console.log("Before auth", baseService.getTokenFromLocalStorage());
        }

        if(!baseService.getTokenFromLocalStorage()){
            window.location.href = logout;
        }

        axios.get(url, {
            headers: {
                "Authorization" : "Bearer " + baseService.getTokenFromLocalStorage(),
                'Accept' : 'application/json',
            },
            params: {
                token: baseService.getTokenFromLocalStorage()
            }

        }).then((response) => {
            if(response.data.success === true && response.status === 200){
                if(next !== null){
                    next();
                }else{
                    return null;
                }
            }else{
                window.location.href = logout;
            }
        }).catch((error) => {
            if(error.response){
                window.location.href = logout;
            }
        });
    },

    async checkSession(){

        if(!baseService.getTokenFromLocalStorage()){
            return false;
        }

        if(import.meta.env.VITE_APP_ENV === 'local') {
            console.log("Check session");
        }

        try {
            let params = {
                token: baseService.getTokenFromLocalStorage()
            }
            const response = await apiClientAuto.get('/authenticate', { params });

            if(import.meta.env.VITE_APP_ENV === 'local') {
                console.log("RESPONSE", response.data);
                console.log("PARAMS", params);
            }

            return response.data?.success === true;

        } catch (error) {
            if (error?.response) {
                if(config.APP_ENV === 'local'){
                    console.log("Error response", error.response);
                }

                if (Object.keys(error.response?.data?.errors || {}).length > 0) {
                    errors.value = error.response?.data?.errors;
                    if(config.APP_ENV === 'local'){
                        console.log("Validation Errors", error.value);
                    }
                }

                if (error.response?.data?.server_error) {
                    errors.value.server_error = 'Server error. Please try again later or contact your admin.';
                }
            }
            return false;
        }
    }

}

export default RouteService;
