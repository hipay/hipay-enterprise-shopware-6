import Plugin from 'src/plugin-system/plugin.class';
import LoadingIndicatorUtil from 'src/utility/loading-indicator/loading-indicator.util';
import HttpClient from 'src/service/http-client.service';


/**
 * Plugin hipay for credit card
 */
export default class HipayManageCreditcardPlugin extends Plugin {

  init() {

    this._client = new HttpClient();

    // register the events
    this._registerEvents();
  }

  _registerEvents() {

    this.el.querySelectorAll('.delete-card').forEach(element => {
      element.addEventListener('click', btn => this.onDeleteButtonClick(element))
    });
  }

  onDeleteButtonClick(element) {
    const text = element.querySelector('span');
    const loader = new LoadingIndicatorUtil(element);
    const initialDisplayIcon =  text.style.display;

    const onError = () => {
      text.style.display = initialDisplayIcon;
      loader.remove();
    };

    text.style.display = 'none';
    loader.create();


    this._client.delete(`/account/creditcard/${element.dataset.id}`,'', (
      response => {
        try{
          response = JSON.parse(response);

          if(!response.success) {
            throw new Error();
          }

          element.closest(".hipay-token-label").remove();
        } catch (error) {
          onError();
        }    
      }
    ));
     
  }

}