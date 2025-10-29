import './scss/_saved-cards.scss';
import SquarePaymentsCreditCard from "./squarepayments/squarepayments-credit-card.plugin";
import SquarePaymentsSavedCards from "./squarepayments/squarepayments-saved-cards.plugin";


const PluginManager = window.PluginManager;
PluginManager.register('SquarePaymentsCreditCard', SquarePaymentsCreditCard,    '[data-square-payments-credit-card]');
PluginManager.register('SquarePaymentsSavedCards', SquarePaymentsSavedCards,    '[data-square-payments-saved-cards]');
