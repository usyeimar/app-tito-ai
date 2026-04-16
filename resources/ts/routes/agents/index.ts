import config from './config'
import testCall from './test-call'

const agents = {
    config: Object.assign(config, config),
    testCall: Object.assign(testCall, testCall),
}

export default agents