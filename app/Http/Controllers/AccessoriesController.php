<?php
namespace App\Http\Controllers;

use App\Helpers\Helper;
use App\Models\Accessory;
use App\Models\Company;
use App\Models\User;
use Auth;
use Carbon\Carbon;
use Config;
use DB;
use Gate;
use Input;
use Lang;
use Mail;
use Redirect;
use Illuminate\Http\Request;
use Slack;
use Str;
use View;

/** This controller handles all actions related to Accessories for
 * the Snipe-IT Asset Management application.
 *
 * @version    v1.0
 */
class AccessoriesController extends Controller
{

    /**
    * Returns a view that invokes the ajax tables which actually contains
    * the content for the accessories listing, which is generated in getDatatable.
    *
    * @author [A. Gianotto] [<snipe@snipe.net>]
    * @see AccessoriesController::getDatatable() method that generates the JSON response
    * @since [v1.0]
    * @return View
    */
    public function index(Request $request)
    {
        $this->authorize('index', Accessory::class);
        return view('accessories/index');
    }


  /**
   * Returns a view with a form to create a new Accessory.
   *
   * @author [A. Gianotto] [<snipe@snipe.net>]
   * @return View
   */
    public function create(Request $request)
    {
        $this->authorize('create', Accessory::class);
        // Show the page
        return view('accessories/edit')
          ->with('item', new Accessory)
          ->with('category_list', Helper::categoryList('accessory'))
          ->with('company_list', Helper::companyList())
          ->with('location_list', Helper::locationsList())
          ->with('manufacturer_list', Helper::manufacturerList());
    }


  /**
   * Validate and save new Accessory from form post
   *
   * @author [A. Gianotto] [<snipe@snipe.net>]
   * @return Redirect
   */
    public function store(Request $request)
    {
        $this->authorize(Accessory::class);
        // create a new model instance
        $accessory = new Accessory();

        // Update the accessory data
        $accessory->name                    = request('name');
        $accessory->category_id             = request('category_id');
        $accessory->location_id             = request('location_id');
        $accessory->min_amt                 = request('min_amt');
        $accessory->company_id              = Company::getIdForCurrentUser(request('company_id'));
        $accessory->order_number            = request('order_number');
        $accessory->manufacturer_id         = request('manufacturer_id');
        $accessory->model_number            = request('model_number');
        $accessory->purchase_date           = request('purchase_date');
        $accessory->purchase_cost           = Helper::ParseFloat(request('purchase_cost'));
        $accessory->qty                     = request('qty');
        $accessory->user_id                 = Auth::user()->id;

        // Was the accessory created?
        if ($accessory->save()) {
            // Redirect to the new accessory  page
            return redirect()->route('accessories.index')->with('success', trans('admin/accessories/message.create.success'));
        }
        return redirect()->back()->withInput()->withErrors($accessory->getErrors());
    }

  /**
   * Return view for the Accessory update form, prepopulated with existing data
   *
   * @author [A. Gianotto] [<snipe@snipe.net>]
   * @param  int  $accessoryId
   * @return View
   */
    public function edit(Request $request, $accessoryId = null)
    {
        // Check if the accessory exists
        if (is_null($item = Accessory::find($accessoryId))) {
            return redirect()->route('accessories.index')->with('error', trans('admin/accessories/message.does_not_exist'));
        }

        $this->authorize($item);

        return view('accessories/edit', compact('item'))
          ->with('category_list', Helper::categoryList('accessory'))
          ->with('company_list', Helper::companyList())
          ->with('location_list', Helper::locationsList())
          ->with('manufacturer_list', Helper::manufacturerList());
    }


  /**
   * Save edited Accessory from form post
   *
   * @author [A. Gianotto] [<snipe@snipe.net>]
   * @param  int  $accessoryId
   * @return Redirect
   */
    public function update(Request $request, $accessoryId = null)
    {
        if (is_null($accessory = Accessory::find($accessoryId))) {
            return redirect()->route('accessories.index')->with('error', trans('admin/accessories/message.does_not_exist'));
        }

        $this->authorize($accessory);

        // Update the accessory data
        $accessory->name                    = request('name');
        $accessory->location_id             = request('location_id');
        $accessory->min_amt                 = request('min_amt');
        $accessory->category_id             = request('category_id');
        $accessory->company_id              = Company::getIdForCurrentUser(request('company_id'));
        $accessory->manufacturer_id         = request('manufacturer_id');
        $accessory->order_number            = request('order_number');
        $accessory->model_number            = request('model_number');
        $accessory->purchase_date       = request('purchase_date');
        $accessory->purchase_cost       = request('purchase_cost');
        $accessory->qty                     = request('qty');

      // Was the accessory updated?
        if ($accessory->save()) {
            return redirect()->route('accessories.index')->with('success', trans('admin/accessories/message.update.success'));
        }
        return redirect()->back()->withInput()->withErrors($accessory->getErrors());
    }

  /**
   * Delete the given accessory.
   *
   * @author [A. Gianotto] [<snipe@snipe.net>]
   * @param  int  $accessoryId
   * @return Redirect
   */
    public function destroy(Request $request, $accessoryId)
    {
        if (is_null($accessory = Accessory::find($accessoryId))) {
            return redirect()->route('accessories.index')->with('error', trans('admin/accessories/message.not_found'));
        }

        $this->authorize($accessory);


        if ($accessory->hasUsers() > 0) {
             return redirect()->route('accessories.index')->with('error', trans('admin/accessories/message.assoc_users', array('count'=> $accessory->hasUsers())));
        }
        $accessory->delete();
        return redirect()->route('accessories.index')->with('success', trans('admin/accessories/message.delete.success'));
    }



  /**
  * Returns a view that invokes the ajax table which  contains
  * the content for the accessory detail view, which is generated in getDataView.
  *
  * @author [A. Gianotto] [<snipe@snipe.net>]
  * @param  int  $accessoryID
  * @see AccessoriesController::getDataView() method that generates the JSON response
  * @since [v1.0]
  * @return View
  */
    public function show(Request $request, $accessoryID = null)
    {
        $accessory = Accessory::find($accessoryID);
        $this->authorize('view', $accessory);
        if (isset($accessory->id)) {
            return view('accessories/view', compact('accessory'));
        }
        // Prepare the error message
        $error = trans('admin/accessories/message.does_not_exist', compact('id'));

        // Redirect to the user management page
        return redirect()->route('accessories')->with('error', $error);
    }

  /**
   * Return the form to checkout an Accessory to a user.
   *
   * @author [A. Gianotto] [<snipe@snipe.net>]
   * @param  int  $accessoryId
   * @return View
   */
    public function getCheckout(Request $request, $accessoryId)
    {
        // Check if the accessory exists
        if (is_null($accessory = Accessory::find($accessoryId))) {
            // Redirect to the accessory management page with error
            return redirect()->route('accessories.index')->with('error', trans('admin/accessories/message.not_found'));
        }

        $this->authorize('checkout', $accessory);

        // Get the dropdown of users and then pass it to the checkout view
        return view('accessories/checkout', compact('accessory'))->with('users_list', Helper::usersList());

    }

  /**
   * Save the Accessory checkout information.
   *
   * If Slack is enabled and/or asset acceptance is enabled, it will also
   * trigger a Slack message and send an email.
   *
   * @author [A. Gianotto] [<snipe@snipe.net>]
   * @param  int  $accessoryId
   * @return Redirect
   */
    public function postCheckout(Request $request, $accessoryId)
    {
      // Check if the accessory exists
        if (is_null($accessory = Accessory::find($accessoryId))) {
            // Redirect to the accessory management page with error
            return redirect()->route('accessories.index')->with('error', trans('admin/accessories/message.user_not_found'));
        }

        $this->authorize('checkout', $accessory);

        if (!$user = User::find(Input::get('assigned_to'))) {
            return redirect()->route('accessories.index')->with('error', trans('admin/accessories/message.not_found'));
        }

      // Update the accessory data
        $accessory->assigned_to = e(Input::get('assigned_to'));

        $accessory->users()->attach($accessory->id, [
            'accessory_id' => $accessory->id,
            'created_at' => Carbon::now(),
            'user_id' => Auth::id(),
            'assigned_to' => $request->get('assigned_to')
        ]);

        $logaction = $accessory->logCheckout(e(Input::get('note')), $user);

        DB::table('accessories_users')->where('assigned_to', '=', $accessory->assigned_to)->where('accessory_id', '=', $accessory->id)->first();

        $data['log_id'] = $logaction->id;
        $data['eula'] = $accessory->getEula();
        $data['first_name'] = $user->first_name;
        $data['item_name'] = $accessory->name;
        $data['checkout_date'] = $logaction->created_at;
        $data['item_tag'] = '';
        $data['expected_checkin'] = '';
        $data['note'] = $logaction->note;
        $data['require_acceptance'] = $accessory->requireAcceptance();
        // TODO: Port this to new mail notifications
        if ((($accessory->requireAcceptance()=='1')  || ($accessory->getEula())) && ($user->email!='')) {

            Mail::send('emails.accept-accessory', $data, function ($m) use ($user) {
                $m->to($user->email, $user->first_name . ' ' . $user->last_name);
                $m->replyTo(config('mail.reply_to.address'), config('mail.reply_to.name'));
                $m->subject(trans('mail.Confirm_accessory_delivery'));
            });
        }

      // Redirect to the new accessory page
        return redirect()->route('accessories.index')->with('success', trans('admin/accessories/message.checkout.success'));
    }


    /**
     * Check the accessory back into inventory
     *
     * @author [A. Gianotto] [<snipe@snipe.net>]
     * @param Request $request
     * @param integer $accessoryUserId
     * @param string $backto
     * @return View
     * @internal param int $accessoryId
     */
    public function getCheckin(Request $request, $accessoryUserId = null, $backto = null)
    {
        // Check if the accessory exists
        if (is_null($accessory_user = DB::table('accessories_users')->find($accessoryUserId))) {
            // Redirect to the accessory management page with error
            return redirect()->route('accessories.index')->with('error', trans('admin/accessories/message.not_found'));
        }

        $accessory = Accessory::find($accessory_user->accessory_id);
        $this->authorize('checkin', $accessory);
        return view('accessories/checkin', compact('accessory'))->with('backto', $backto);
    }


    /**
     * Check in the item so that it can be checked out again to someone else
     *
     * @uses Accessory::checkin_email() to determine if an email can and should be sent
     * @author [A. Gianotto] [<snipe@snipe.net>]
     * @param Request $request
     * @param integer $accessoryUserId
     * @param string $backto
     * @return Redirect
     * @internal param int $accessoryId
     */
    public function postCheckin(Request $request, $accessoryUserId = null, $backto = null)
    {
      // Check if the accessory exists
        if (is_null($accessory_user = DB::table('accessories_users')->find($accessoryUserId))) {
            // Redirect to the accessory management page with error
            return redirect()->route('accessories.index')->with('error', trans('admin/accessories/message.not_found'));
        }

        $accessory = Accessory::find($accessory_user->accessory_id);

        $this->authorize('checkin', $accessory);

        $return_to = e($accessory_user->assigned_to);
        $logaction = $accessory->logCheckin(User::find($return_to), e(Input::get('note')));

        // Was the accessory updated?
        if (DB::table('accessories_users')->where('id', '=', $accessory_user->id)->delete()) {
            if (!is_null($accessory_user->assigned_to)) {
                $user = User::find($accessory_user->assigned_to);
            }

            $data['log_id'] = $logaction->id;
            $data['first_name'] = e($user->first_name);
            $data['item_name'] = e($accessory->name);
            $data['checkin_date'] = e($logaction->created_at);
            $data['item_tag'] = '';
            $data['note'] = e($logaction->note);

            if ((($accessory->checkin_email()=='1')) && ($user->email!='')) {

                Mail::send('emails.checkin-asset', $data, function ($m) use ($user) {
                    $m->to($user->email, $user->first_name . ' ' . $user->last_name);
                    $m->replyTo(config('mail.reply_to.address'), config('mail.reply_to.name'));
                    $m->subject(trans('mail.Confirm_Accessory_Checkin'));
                });
            }

            if ($backto=='user') {
                return redirect()->route("users.show", $return_to)->with('success', trans('admin/accessories/message.checkin.success'));
            }
            return redirect()->route("accessories.show", $accessory->id)->with('success', trans('admin/accessories/message.checkin.success'));
        }
        // Redirect to the accessory management page with error
        return redirect()->route('accessories.index')->with('error', trans('admin/accessories/message.checkin.error'));
    }


}
