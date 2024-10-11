<?php

namespace App\Http\Controllers;

use App\Models\Book;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * @OA\Tag(
 *     name="Libros",
 *     description="Operaciones sobre libros (CRUD)"
 * )
 */
class BookController extends Controller
{
    // Crear un libro (permitido para Admin y User)
    /**
     * @OA\Post(
     *     path="/api/books",
     *     summary="Crear un nuevo libro",
     *     tags={"Libros"},
     *     security={{ "bearerAuth":{} }},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"title", "secret"},
     *             @OA\Property(property="title", type="string", example="Mi primer libro"),
     *             @OA\Property(property="secret", type="string", example="Este es mi secreto")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Libro creado exitosamente",
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="integer"),
     *             @OA\Property(property="title", type="string"),
     *             @OA\Property(property="secret", type="string"),
     *             @OA\Property(property="created_at", type="string", format="date-time"),
     *             @OA\Property(property="updated_at", type="string", format="date-time")
     *         )
     *     ),
     *     @OA\Response(response=401, description="No autorizado"),
     *     @OA\Response(response=422, description="Error de validación")
     * )
     */
    public function store(Request $request)
    {
        // Permitir que tanto Admin como User creen libros
        $request->validate([
            'title' => 'required|string|max:255',
            'secret' => 'required|string',
        ]);

        $book = Book::create([
            'user_id' => Auth::id(),  // Asociamos el libro al usuario autenticado
            'title' => $request->title,
            'secret' => $request->secret,
        ]);

        return response()->json($book, 201);
    }

    // Listar todos los libros (sin mostrar el campo secreto) permitido para Admin y User
    public function index()
    {
        // Excluimos el campo 'secret' al listar todos los libros
        $books = Book::select('id', 'user_id', 'title', 'created_at', 'updated_at')->get();
        return response()->json($books);
    }

    // Mostrar un libro por su identificador (permitido para Admin y User)
    public function show($id)
    {
        // Obtener el usuario autenticado
        $user = auth('api')->user();

        // Buscar el libro
        $book = Book::findOrFail($id);

        // Verificar si el usuario autenticado es el dueño del libro
        if ((int)$book->user_id !== $user->id) {
            return response()->json([
                'error' => 'Unauthorized access to book information'
            ], 403); // Prohibir acceso
        }

        return response()->json($book);
    }

    // Eliminar un libro (solo permitido para Admin)
    /**
     * @OA\Delete(
     *     path="/api/books/{id}",
     *     summary="Eliminar un libro",
     *     tags={"Libros"},
     *     security={{ "bearerAuth":{} }},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID del libro",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Libro eliminado exitosamente",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Libro eliminado")
     *         )
     *     ),
     *     @OA\Response(response=403, description="No autorizado para eliminar libros"),
     *     @OA\Response(response=404, description="Libro no encontrado")
     * )
     */
    public function destroy($id)
    {
        $user = auth('api')->user();

        // Solo el rol Admin puede eliminar libros
        // Este chequeo ya está asegurado en el middleware, pero lo dejamos por seguridad extra
        if ($user->role !== 'Admin') {
            return response()->json(['error' => 'No tienes permisos para eliminar libros'], 403);
        }

        $book = Book::findOrFail($id);

        // Verificamos si el libro existe
        $book->delete();

        return response()->json(['message' => 'Libro eliminado'], 200);
    }
}
