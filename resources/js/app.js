import "./bootstrap";
import "./echo";

import Alpine from "alpinejs";
import { shareProfile } from "./share-profile.js";

window.Alpine = Alpine;

Alpine.data("shareProfile", shareProfile);

Alpine.start();
