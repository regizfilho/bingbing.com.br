import "./bootstrap";
import "./echo";
import './push-notifications';
import { requestNotificationPermission } from './notification-permission';

import Alpine from "alpinejs";
import { shareProfile } from "./share-profile.js";


window.requestNotificationPermission = requestNotificationPermission;

window.Alpine = Alpine;

Alpine.data("shareProfile", shareProfile);

Alpine.start();
