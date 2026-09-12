<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Contact;
use App\Models\Tag;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminController extends Controller
{
    public function index(Request $request): View
    {
        $contacts = $this->searchQuery($request)->paginate(7);
        $categories = Category::orderBy('id')->get();
        $tags = Tag::orderBy('id')->get();

        return view('admin.index', compact('contacts', 'categories', 'tags'));
    }

    public function show(Contact $contact): View
    {
        $contact->load(['category', 'tags']);

        return view('admin.show', compact('contact'));
    }

    public function destroy(Contact $contact): RedirectResponse
    {
        $contact->delete();

        return redirect()->route('admin.index');
    }

    public function export(Request $request): StreamedResponse
    {
        $query = $this->searchQuery($request);
        $filename = 'contacts_'.now()->format('Ymd_His').'.csv';

        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');

            // Excelで日本語を認識しやすくするUTF-8の目印
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, [
                'ID',
                '姓',
                '名',
                '性別',
                'メールアドレス',
                '電話番号',
                '住所',
                '建物名',
                'お問い合わせの種類',
                'タグ',
                'お問い合わせ内容',
                '登録日時',
            ], ',', '"', '');

            $genderLabels = [1 => '男性', 2 => '女性', 3 => 'その他'];

            foreach ($query->lazy(200) as $contact) {
                $row = [
                    $contact->id,
                    $contact->first_name,
                    $contact->last_name,
                    $genderLabels[$contact->gender] ?? '',
                    $contact->email,
                    $contact->tel,
                    $contact->address,
                    $contact->building ?? '',
                    $contact->category->content ?? '',
                    $contact->tags->pluck('name')->join(', '),
                    $contact->detail,
                    $contact->created_at?->format('Y-m-d H:i:s') ?? '',
                ];

                // 入力された文字列が表計算ソフトで数式になるのを防ぐ
                $row = array_map(function ($value) {
                    $value = (string) $value;

                    if (preg_match('/^[\s]*[=+\-@]|^[\t\r\n]/u', $value)) {
                        return "'".$value;
                    }

                    return $value;
                }, $row);

                fputcsv($handle, $row, ',', '"', '');
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function searchQuery(Request $request): Builder
    {
        $query = Contact::with(['category', 'tags'])
            ->latest()
            ->orderByDesc('id');

        if ($request->filled('keyword')) {
            $keyword = $request->input('keyword');

            $query->where(function ($q) use ($keyword) {
                $q->where('first_name', 'like', "%{$keyword}%")
                    ->orWhere('last_name', 'like', "%{$keyword}%")
                    ->orWhere('email', 'like', "%{$keyword}%");
            });
        }

        if (in_array($request->gender, ['1', '2', '3'], true)) {
            $query->where('gender', $request->gender);
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->filled('date')) {
            $query->whereDate('created_at', $request->date);
        }

        return $query;
    }
}
