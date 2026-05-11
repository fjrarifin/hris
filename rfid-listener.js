// simple Node.js daemon that reads from a serial USB device and POSTs to Laravel
// requires: npm install serialport axios

const SerialPort = require('serialport');
const Readline = require('@serialport/parser-readline');
const axios = require('axios');

// change to the correct device path, e.g. /dev/ttyUSB0 or /dev/ttyACM0
const DEVICE = process.env.RFID_DEVICE || '/dev/ttyUSB0';
const API_URL = process.env.RFID_API || 'http://localhost/api/rfid/scan';

const port = new SerialPort(DEVICE, { baudRate: 9600 });
const parser = port.pipe(new Readline({ delimiter: '\r\n' }));

parser.on('data', async (line) => {
    const tag = line.trim();
    if (!tag) return;
    console.log('scanned', tag);
    try {
        const res = await axios.post(API_URL, { tag });
        console.log('server responded', res.data);
    } catch (err) {
        console.error('failed to POST', err.message);
    }
});

port.on('error', (err) => {
    console.error('serial error', err.message);
});
