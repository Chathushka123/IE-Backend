<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\User;
use App\Http\Resources\UserResource;
use App\Http\Repositories\UserRepository;
use App\Http\Repositories\Utilities;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use PDF;

class UserController extends Controller
{
    private $repo;

    public function __construct()
    {
        $this->repo = new UserRepository();
    }

    /**
     * Typeahead lookup of active users by name or email (min 3 chars, max 20 results).
     * Returns only id/name/email — never the full User/UserResource, which carry the
     * password hash. Already narrowed to the request's active factories by the
     * ScopedToFactories global scope; `factory_id` narrows further to one factory.
     */
    public function search(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'term' => 'required|string|min:3|max:100',
            'factory_id' => 'nullable|integer',
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => $validator->errors()->first()], 422);
        }

        // Escape LIKE wildcards so a typed "%" or "_" matches literally.
        $term = '%' . addcslashes($request->input('term'), '%_\\') . '%';
        $factoryId = $request->input('factory_id');

        $users = User::query()
            ->select('id', 'name', 'email')
            ->where('is_active', true)
            ->where(function ($q) use ($term) {
                $q->where('name', 'like', $term)->orWhere('email', 'like', $term);
            })
            ->when($factoryId, function ($q) use ($factoryId) {
                $q->whereHas('factories', fn ($f) => $f->where('factories.id', $factoryId));
            })
            ->orderBy('name')
            ->limit(20)
            ->get();

        return response()->json(['status' => 'success', 'data' => $users], 200);
    }

    public function changePassword(Request $request)
    {
        $user_id = $request->user_id;
        $password = $request->password;
        $updated_at = $request->updated_at;
        return $this->repo->changePassword($user_id, $password, $updated_at);
    }

    /**
     * Self-service password change for a user before they have a JWT
     * (e.g. forced/first-time password reset). Identifies the user by
     * email + current password rather than the auth:api guard.
     */
    public function changePasswordByUserSelf(Request $request)
    {
        $validator = Validator::make(
            $request->all(),
            [
                'email' => 'required|string|email',
                'currentPassword' => 'required|string',
                'newPassword' => 'required|string|min:8|different:currentPassword',
            ]
        );
        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => $validator->errors()->first()], 422);
        }

        UserRepository::changePasswordBySelf(
            $request->input('email'),
            $request->input('currentPassword'),
            $request->input('newPassword')
        );

        return response()->json(['status' => 'success', 'message' => 'Password changed successfully']);
    }

    public function printStickers($id)
    {
        $users = User::where('id', '=', $id)->get();
        $pdf = PDF::loadView('print.users_stickers', ['users' => $users]);
        $pdf->setPaper('A4', 'portrait');
        return $pdf->stream('users_stickers_' . date('Y_m_d_H_i_s') . '.pdf');
    }
}
