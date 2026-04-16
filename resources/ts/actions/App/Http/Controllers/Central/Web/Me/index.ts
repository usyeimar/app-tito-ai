import ProfileController from './ProfileController'
import SecurityController from './SecurityController'

const Me = {
    ProfileController: Object.assign(ProfileController, ProfileController),
    SecurityController: Object.assign(SecurityController, SecurityController),
}

export default Me