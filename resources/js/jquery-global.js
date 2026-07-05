// Make jQuery global BEFORE plugins (select2, datatables) are imported, so they
// can attach themselves to the same jQuery instance. ES module imports execute
// in order, so importing this module before the plugins guarantees the global
// is set in time.
import $ from 'jquery';

window.$ = window.jQuery = $;

export default $;
